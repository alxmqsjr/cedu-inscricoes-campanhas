<?php
/**
 * Comandos WP-CLI: wp inscricoes <comando>.
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

use WP_CLI;
use WP_CLI\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Gerencia as inscrições de campanhas pela linha de comando.
 */
final class Cli {

	/**
	 * Importa inscrições de um arquivo JSON (um objeto ou uma lista de objetos),
	 * no mesmo formato enviado pelo formulário.
	 *
	 * ## OPTIONS
	 *
	 * <arquivo>
	 * : Caminho do arquivo JSON.
	 *
	 * ## EXAMPLES
	 *
	 *     wp inscricoes importar inscricao.json
	 *
	 * @param string[] $args Argumentos.
	 */
	public function importar( array $args ): void {
		$arquivo = $args[0];
		if ( ! is_readable( $arquivo ) ) {
			WP_CLI::error( "Não foi possível ler {$arquivo}." );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- arquivo local.
		$dados = json_decode( (string) file_get_contents( $arquivo ), true );
		if ( ! is_array( $dados ) ) {
			WP_CLI::error( 'JSON inválido.' );
		}

		$lista = array_keys( $dados ) === range( 0, count( $dados ) - 1 ) ? $dados : array( $dados );
		$erros = 0;
		foreach ( $lista as $i => $item ) {
			$r = is_array( $item ) ? Importador::importar( $item ) : new \WP_Error( 'item', 'Item não é um objeto.' );
			if ( is_wp_error( $r ) ) {
				++$erros;
				WP_CLI::warning( "Item {$i}: " . $r->get_error_message() );
				continue;
			}
			WP_CLI::log( sprintf( 'Item %d: inscrição #%d %s.', $i, $r['post_id'], $r['criada'] ? 'criada' : 'atualizada' ) );
		}

		$erros ? WP_CLI::error( "{$erros} item(ns) com erro." ) : WP_CLI::success( count( $lista ) . ' item(ns) importado(s).' );
	}

	/**
	 * Lista inscrições.
	 *
	 * ## OPTIONS
	 *
	 * [--edicao=<slug>]
	 * : Filtra pela edição.
	 *
	 * [--status=<slug>]
	 * : Filtra pelo status.
	 *
	 * [--busca=<texto>]
	 * : Título, e-mail, nome, instituição, município ou ID do formulário.
	 *
	 * [--duplicadas]
	 * : Só as possíveis duplicadas (mesmo título e e-mail).
	 *
	 * [--format=<formato>]
	 * : table, csv, json, ids ou count.
	 * ---
	 * default: table
	 * ---
	 *
	 * @param string[]             $args  Argumentos.
	 * @param array<string, mixed> $assoc Opções.
	 */
	public function listar( array $args, array $assoc ): void {
		$filtros = self::filtros( $assoc );
		$formato = (string) ( $assoc['format'] ?? 'table' );

		if ( 'count' === $formato ) {
			WP_CLI::log( (string) Repositorio::contar( $filtros ) );
			return;
		}

		$ids = Repositorio::ids( $filtros, PHP_INT_MAX, 0, 'ID', 'ASC' );
		if ( 'ids' === $formato ) {
			WP_CLI::log( implode( ' ', $ids ) );
			return;
		}

		_prime_post_caches( $ids, true, true );
		$linhas = array_map(
			static fn( int $id ): array => array(
				'ID'         => $id,
				'titulo'     => get_the_title( $id ),
				'formulario' => (string) get_post_meta( $id, Esquema::META_ID_EXTERNO, true ),
				'email'      => (string) get_post_meta( $id, 'email', true ),
				'municipio'  => (string) get_post_meta( $id, 'municipio', true ),
				'pontuacao'  => (string) get_post_meta( $id, 'pontuacao', true ),
			),
			$ids
		);

		Utils\format_items( $formato, $linhas, array( 'ID', 'titulo', 'formulario', 'email', 'municipio', 'pontuacao' ) );
	}

	/**
	 * Apaga inscrições de vez, com a mídia delas.
	 *
	 * ## OPTIONS
	 *
	 * [<id>...]
	 * : IDs das inscrições.
	 *
	 * [--todas]
	 * : Todas as que atendem aos filtros (sem filtros: todas as inscrições).
	 *
	 * [--edicao=<slug>]
	 * : Com --todas, filtra pela edição.
	 *
	 * [--status=<slug>]
	 * : Com --todas, filtra pelo status.
	 *
	 * [--dry-run]
	 * : Só mostra o que seria apagado.
	 *
	 * [--yes]
	 * : Não pede confirmação.
	 *
	 * ## EXAMPLES
	 *
	 *     wp inscricoes apagar 123 456
	 *     wp inscricoes apagar --todas --edicao=2026 --dry-run
	 *
	 * @param string[]             $args  Argumentos.
	 * @param array<string, mixed> $assoc Opções.
	 */
	public function apagar( array $args, array $assoc ): void {
		$todas  = ! empty( $assoc['todas'] );
		$tem_id = ! empty( $args );
		if ( ( $todas && $tem_id ) || ( ! $todas && ! $tem_id ) ) {
			WP_CLI::error( 'Informe IDs ou --todas (um dos dois).' );
		}

		$ids = $todas
			? Repositorio::ids( self::filtros( $assoc ), PHP_INT_MAX, 0, 'ID', 'ASC' )
			: array_values( array_filter( array_map( 'absint', $args ), static fn( int $id ): bool => get_post_type( $id ) === Esquema::POST_TYPE ) );

		if ( ! $ids ) {
			WP_CLI::success( 'Nenhuma inscrição a apagar.' );
			return;
		}

		if ( ! empty( $assoc['dry-run'] ) ) {
			WP_CLI::log( count( $ids ) . ' inscrição(ões) seriam apagadas: ' . implode( ' ', $ids ) );
			return;
		}

		WP_CLI::confirm( sprintf( 'Apagar de vez %d inscrição(ões) e a mídia delas?', count( $ids ) ), $assoc );

		$barra    = Utils\make_progress_bar( 'Apagando', count( $ids ) );
		$apagadas = 0;
		$midias   = 0;
		foreach ( $ids as $id ) {
			$r = Repositorio::apagar( $id );
			if ( $r['ok'] ) {
				++$apagadas;
				$midias += $r['midias'];
			} else {
				WP_CLI::warning( "Falha ao apagar #{$id}." );
			}
			$barra->tick();
		}
		$barra->finish();

		WP_CLI::success( "{$apagadas} inscrição(ões) e {$midias} mídia(s) apagadas." );
	}

	/**
	 * Mostra a situação da chave da API ou gera uma nova.
	 *
	 * ## OPTIONS
	 *
	 * <acao>
	 * : status ou gerar.
	 * ---
	 * options:
	 *   - status
	 *   - gerar
	 * ---
	 *
	 * [--yes]
	 * : Não pede confirmação ao gerar.
	 *
	 * @param string[]             $args  Argumentos.
	 * @param array<string, mixed> $assoc Opções.
	 */
	public function chave( array $args, array $assoc ): void {
		$origem = Autenticacao::origem();

		if ( 'status' === $args[0] ) {
			WP_CLI::log( '' !== $origem ? "Chave configurada (origem: {$origem})." : 'Nenhuma chave configurada: a API recusa todos os envios.' );
			return;
		}

		if ( 'constante' === $origem ) {
			WP_CLI::error( 'A chave está definida no wp-config.php; troque-a lá.' );
		}

		WP_CLI::confirm( 'Gerar uma nova chave invalida a atual. Continuar?', $assoc );
		WP_CLI::log( Autenticacao::gerar() );
		WP_CLI::success( 'Chave gerada (acima). Ela não será mostrada de novo.' );
	}

	/**
	 * Filtros a partir das opções.
	 *
	 * @param array<string, mixed> $assoc Opções.
	 * @return array<string, string>
	 */
	private static function filtros( array $assoc ): array {
		return array(
			'edicao'     => (string) ( $assoc['edicao'] ?? '' ),
			'status'     => (string) ( $assoc['status'] ?? '' ),
			'busca'      => (string) ( $assoc['busca'] ?? '' ),
			'duplicadas' => empty( $assoc['duplicadas'] ) ? '' : '1',
		);
	}
}
