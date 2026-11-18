<?php
/**
 * Transforma o JSON do formulário externo em uma inscrição (post + metas + mídia).
 *
 * Independe da REST: é usado pelo endpoint e pelo comando WP-CLI. Os nomes
 * dos campos vêm de config/campos.php (classe Esquema).
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Importação de uma inscrição.
 */
final class Importador {

	/** Segundos após os quais uma trava esquecida é descartada. */
	private const TRAVA_EXPIRA = 300;

	/** Prefixo das options de trava. */
	public const PREFIXO_TRAVA = 'cedu_inscricoes_trava_';

	/** Profundidade máxima aceita em listas e objetos. */
	private const PROFUNDIDADE = 10;

	/** Quantos nomes de campos ignorados são guardados. */
	private const MAX_IGNORADOS = 50;

	/**
	 * Cria ou atualiza a inscrição descrita por $dados.
	 *
	 * @param array<string, mixed> $dados JSON decodificado.
	 * @return array{criada: bool, post_id: int}|WP_Error
	 */
	public static function importar( array $dados ) {
		if ( empty( $dados ) ) {
			return new WP_Error( 'dados_vazios', __( 'Nenhum dado recebido.', 'cedu-inscricoes-campanhas' ), array( 'status' => 400 ) );
		}

		$dados = self::aplicar_apelidos( $dados );
		self::registrar_ignorados( $dados );

		$titulo = self::texto( $dados[ Esquema::campo( 'titulo' ) ] ?? '' );
		if ( '' === $titulo ) {
			$titulo = __( 'Inscrição sem título', 'cedu-inscricoes-campanhas' );
		}

		$id_externo = self::texto( $dados[ Esquema::papel( 'id' )['campo'] ?? '' ] ?? '' );

		$trava = '';
		if ( '' !== $id_externo ) {
			$trava = self::travar( $id_externo );
			if ( '' === $trava ) {
				return new WP_Error( 'em_processamento', __( 'Esta inscrição já está sendo processada. Tente novamente em instantes.', 'cedu-inscricoes-campanhas' ), array( 'status' => 409 ) );
			}
		}

		try {
			return self::gravar( $dados, $titulo, $id_externo );
		} finally {
			if ( '' !== $trava ) {
				delete_option( $trava );
			}
		}
	}

	/**
	 * Troca os nomes novos (apelidos) pelos nomes configurados.
	 * Se os dois vierem, vale o nome configurado.
	 *
	 * @param array<string, mixed> $dados JSON decodificado.
	 * @return array<string, mixed>
	 */
	private static function aplicar_apelidos( array $dados ): array {
		foreach ( Esquema::apelidos() as $novo => $configurado ) {
			if ( array_key_exists( $novo, $dados ) ) {
				if ( ! array_key_exists( $configurado, $dados ) ) {
					$dados[ $configurado ] = $dados[ $novo ];
				}
				unset( $dados[ $novo ] );
			}
		}
		return $dados;
	}

	/**
	 * Guarda os NOMES (nunca os valores) dos campos recebidos que não estão em
	 * config/campos.php, para aparecerem em Informações.
	 *
	 * @param array<string, mixed> $dados JSON decodificado.
	 */
	private static function registrar_ignorados( array $dados ): void {
		$conhecidos = array_flip( Esquema::campos_conhecidos() );
		$novos      = array();

		foreach ( array_keys( $dados ) as $chave ) {
			$chave = (string) $chave;
			if ( isset( $conhecidos[ $chave ] ) ) {
				continue;
			}
			// Nome exibido no painel: só caracteres seguros e tamanho limitado.
			$nome = substr( (string) preg_replace( '/[^A-Za-z0-9_.-]/', '?', $chave ), 0, 64 );
			if ( '' !== $nome ) {
				$novos[ $nome ] = gettype( $dados[ $chave ] );
			}
		}

		if ( ! $novos ) {
			return;
		}

		$registro = get_option( Config::OPCAO_CAMPOS_IGNORADOS, array() );
		$registro = is_array( $registro ) ? $registro : array();
		foreach ( $novos as $nome => $tipo ) {
			$registro[ $nome ] = array(
				'tipo'   => $tipo,
				'vezes'  => (int) ( $registro[ $nome ]['vezes'] ?? 0 ) + 1,
				'ultima' => time(),
			);
		}

		// Mantém só os mais recentes.
		uasort( $registro, static fn( $a, $b ) => (int) $b['ultima'] <=> (int) $a['ultima'] );
		update_option( Config::OPCAO_CAMPOS_IGNORADOS, array_slice( $registro, 0, self::MAX_IGNORADOS, true ), false );
	}

	/**
	 * Grava a inscrição (já com a trava obtida).
	 *
	 * @param array<string, mixed> $dados      JSON decodificado.
	 * @param string               $titulo     Título da campanha.
	 * @param string               $id_externo Identificador do envio.
	 * @return array{criada: bool, post_id: int}|WP_Error
	 */
	private static function gravar( array $dados, string $titulo, string $id_externo ) {
		$post_id = Repositorio::buscar_por_id_externo( $id_externo );
		$criada  = 0 === $post_id;
		$antigos = array();

		if ( $criada ) {
			// wp_insert_post()/wp_update_post() esperam dados com barras (wp_slash).
			$post_id = wp_insert_post(
				wp_slash(
					array(
						'post_title'  => $titulo,
						'post_type'   => Esquema::POST_TYPE,
						'post_status' => 'publish',
						'post_author' => Config::autor(),
					)
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				// O detalhe pode conter informação do banco: fica só no log.
				error_log( 'cedu-inscricoes-campanhas: falha ao criar inscrição: ' . $post_id->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				return new WP_Error( 'erro_post', __( 'Não foi possível gravar a inscrição.', 'cedu-inscricoes-campanhas' ), array( 'status' => 500 ) );
			}
		} else {
			wp_update_post(
				wp_slash(
					array(
						'ID'         => $post_id,
						'post_title' => $titulo,
					)
				)
			);

			// A mídia antiga só é apagada no fim, depois que a nova estiver gravada.
			$antigos = Midia::anexos_do_importador( $post_id );

			foreach ( Esquema::todas_as_metas() as $chave ) {
				delete_post_meta( $post_id, $chave );
			}
			delete_post_thumbnail( $post_id );
		}

		if ( '' !== $id_externo && '' !== Esquema::meta_id() ) {
			self::meta( $post_id, Esquema::meta_id(), $id_externo );
		}

		self::gravar_metas( $post_id, $dados );
		self::gravar_imagens_publicas( $post_id, $dados, $titulo );
		self::definir_capa( $post_id, $dados, $titulo );
		self::atribuir_edicao( $post_id );

		Midia::apagar( $antigos );

		return array(
			'criada'  => $criada,
			'post_id' => (int) $post_id,
		);
	}

	/**
	 * Campos de texto, números, listas, objetos, data do envio e evidências.
	 *
	 * @param int                  $post_id ID da inscrição.
	 * @param array<string, mixed> $dados   JSON decodificado.
	 */
	private static function gravar_metas( int $post_id, array $dados ): void {
		foreach ( Esquema::campos( 'texto' ) as $campo ) {
			if ( isset( $dados[ $campo ] ) && is_scalar( $dados[ $campo ] ) ) {
				self::meta( $post_id, $campo, sanitize_textarea_field( (string) $dados[ $campo ] ) );
			}
		}

		foreach ( Esquema::campos( 'numero' ) as $campo ) {
			if ( isset( $dados[ $campo ] ) && is_numeric( $dados[ $campo ] ) ) {
				self::meta( $post_id, $campo, (int) $dados[ $campo ] );
			}
		}

		foreach ( Esquema::campos( 'lista' ) as $campo ) {
			if ( isset( $dados[ $campo ] ) && is_array( $dados[ $campo ] ) ) {
				self::meta( $post_id, $campo, self::json( self::limpar( $dados[ $campo ] ) ) );
			}
		}

		foreach ( Esquema::campos( 'objeto' ) as $campo ) {
			if ( isset( $dados[ $campo ] ) ) {
				self::meta( $post_id, $campo, self::json( self::limpar( $dados[ $campo ] ) ) );
			}
		}

		$enviado = Esquema::papel( 'enviado_em' );
		$valor   = $dados[ $enviado['campo'] ?? '' ] ?? null;
		if ( '' !== ( $enviado['subcampo'] ?? '' ) ) {
			$valor = is_array( $valor ) ? ( $valor[ $enviado['subcampo'] ] ?? null ) : null;
		}
		if ( '' !== ( $enviado['meta'] ?? '' ) && is_numeric( $valor ) ) {
			self::meta( $post_id, $enviado['meta'], (int) $valor );
		}

		// Evidências internas: só URL e legenda, sem baixar as imagens.
		foreach ( Esquema::campos( 'evidencia' ) as $grupo ) {
			if ( empty( $dados[ $grupo ] ) || ! is_array( $dados[ $grupo ] ) ) {
				continue;
			}

			$itens = array();
			foreach ( $dados[ $grupo ] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$url = esc_url_raw( self::texto( $item['url'] ?? '' ), array( 'http', 'https' ) );
				if ( '' === $url ) {
					continue;
				}
				$itens[] = array(
					'url'     => $url,
					'caption' => self::texto( $item['caption'] ?? '' ),
					'local'   => false,
				);
			}

			self::meta( $post_id, $grupo, self::json( $itens ) );
		}
	}

	/**
	 * Baixa as imagens públicas e grava a meta de cada uma.
	 *
	 * Formato da meta (contrato com o tema):
	 * {"attachment_id":123,"url":"…","url_original":"…","caption":"…","source":"…","sourceIndex":0,"local":true}
	 * Se o download falha: attachment_id 0, url = url_original e local false.
	 *
	 * @param int                  $post_id ID da inscrição.
	 * @param array<string, mixed> $dados   JSON decodificado.
	 * @param string               $titulo  Título da campanha.
	 */
	private static function gravar_imagens_publicas( int $post_id, array $dados, string $titulo ): void {
		for ( $n = 1; $n <= Esquema::total_imagens_publicas(); $n++ ) {
			$campo = Esquema::meta_imagem_publica( $n );
			$img   = $dados[ $campo ] ?? null;

			if ( ! is_array( $img ) ) {
				continue;
			}

			$url = esc_url_raw( trim( self::texto( $img['url'] ?? '' ) ), array( 'http', 'https' ) );
			if ( '' === $url ) {
				continue;
			}

			$legenda = self::texto( $img['caption'] ?? '' );
			$origem  = Esquema::nome( self::texto( $img['source'] ?? '' ) );
			$indice  = isset( $img['sourceIndex'] ) && is_numeric( $img['sourceIndex'] ) ? (int) $img['sourceIndex'] : null;

			// Título da mídia (só no admin; a legenda pública continua sendo $legenda):
			// 1) legenda da própria imagem; 2) legenda da evidência de origem;
			// 3) "{título} – Imagem N", para nunca ficar com o nome do arquivo.
			$titulo_midia = $legenda;
			if ( '' === $titulo_midia && '' !== $origem && null !== $indice && is_array( $dados[ $origem ][ $indice ] ?? null ) ) {
				$titulo_midia = self::texto( $dados[ $origem ][ $indice ]['caption'] ?? '' );
			}
			if ( '' === $titulo_midia ) {
				$titulo_midia = trim( $titulo . ' – Imagem ' . $n );
			}

			$anexo = Midia::baixar( $url, $post_id, $titulo_midia );

			self::meta(
				$post_id,
				$campo,
				self::json(
					array(
						'attachment_id' => $anexo,
						'url'           => $anexo ? (string) wp_get_attachment_url( $anexo ) : $url,
						'url_original'  => $url,
						'caption'       => $legenda,
						'source'        => $origem,
						'sourceIndex'   => $indice,
						'local'         => (bool) $anexo,
					)
				)
			);
		}
	}

	/**
	 * Define a imagem de destaque.
	 *
	 * - Se a inscrição oculta as imagens (papel "protecao"): sempre a imagem
	 *   padrão compartilhada, sem usar as imagens do participante.
	 * - Senão: a URL de capa; se faltar ou falhar, a primeira imagem pública.
	 *
	 * @param int                  $post_id ID da inscrição.
	 * @param array<string, mixed> $dados   JSON decodificado.
	 * @param string               $titulo  Título da campanha.
	 */
	private static function definir_capa( int $post_id, array $dados, string $titulo ): void {
		$protecao = Esquema::papel( 'protecao' );
		if ( '' !== ( $protecao['oculta'] ?? '' ) && self::texto( $dados[ $protecao['campo'] ?? '' ] ?? '' ) === $protecao['oculta'] ) {
			$padrao = Midia::imagem_padrao();
			if ( $padrao ) {
				set_post_thumbnail( $post_id, $padrao );
			}
			return;
		}

		$capa_url = esc_url_raw( trim( self::texto( $dados[ Esquema::campo( 'capa' ) ] ?? '' ) ), array( 'http', 'https' ) );
		if ( '' !== $capa_url ) {
			$capa = Midia::baixar( $capa_url, $post_id, $titulo );
			if ( $capa ) {
				set_post_thumbnail( $post_id, $capa );
				return;
			}
		}

		foreach ( Esquema::campos_imagem_publica() as $campo ) {
			$img = json_decode( (string) get_post_meta( $post_id, $campo, true ), true );
			if ( is_array( $img ) && ! empty( $img['local'] ) && ! empty( $img['attachment_id'] ) ) {
				set_post_thumbnail( $post_id, (int) $img['attachment_id'] );
				return;
			}
		}
	}

	/**
	 * Atribui a edição configurada, se a inscrição ainda não tiver nenhuma.
	 * Não cria termos: se o termo não existir, nada é feito.
	 *
	 * @param int $post_id ID da inscrição.
	 */
	private static function atribuir_edicao( int $post_id ): void {
		$slug = Config::edicao();
		if ( '' === $slug || ! taxonomy_exists( Esquema::TAX_EDICAO ) ) {
			return;
		}

		$atuais = wp_get_object_terms( $post_id, Esquema::TAX_EDICAO, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $atuais ) || ! empty( $atuais ) ) {
			return;
		}

		$termo = get_term_by( 'slug', $slug, Esquema::TAX_EDICAO );
		if ( $termo ) {
			wp_set_object_terms( $post_id, array( (int) $termo->term_id ), Esquema::TAX_EDICAO );
		}
	}

	/**
	 * Obtém uma trava por identificador, para que dois envios simultâneos do
	 * mesmo formulário não criem duas inscrições.
	 *
	 * @param string $id_externo Identificador do envio.
	 * @return string Nome da option da trava, ou '' se já está travado.
	 */
	private static function travar( string $id_externo ): string {
		$nome = self::PREFIXO_TRAVA . md5( $id_externo );

		if ( add_option( $nome, time(), '', false ) ) {
			return $nome;
		}

		// Trava esquecida (processo interrompido): descarta e tenta de novo.
		if ( time() - (int) get_option( $nome, 0 ) > self::TRAVA_EXPIRA ) {
			delete_option( $nome );
			if ( add_option( $nome, time(), '', false ) ) {
				return $nome;
			}
		}

		return '';
	}

	/**
	 * Limpa recursivamente listas e objetos: textos sem HTML,
	 * números e booleanos mantidos, chaves inválidas e níveis profundos demais
	 * descartados.
	 *
	 * @param mixed $valor       Valor recebido.
	 * @param int   $profundidade Nível atual.
	 * @return mixed
	 */
	private static function limpar( $valor, int $profundidade = 0 ) {
		if ( is_string( $valor ) ) {
			// Só remove HTML; não usa sanitize_textarea_field(), que apagaria
			// sequências "%xx" e estragaria URLs (ex.: linksMateriais).
			return wp_strip_all_tags( wp_check_invalid_utf8( $valor ) );
		}
		if ( is_int( $valor ) || is_float( $valor ) || is_bool( $valor ) || null === $valor ) {
			return $valor;
		}
		if ( ! is_array( $valor ) || $profundidade >= self::PROFUNDIDADE ) {
			return null;
		}

		$limpo = array();
		foreach ( $valor as $chave => $item ) {
			$chave           = is_int( $chave ) ? $chave : sanitize_text_field( (string) $chave );
			$limpo[ $chave ] = self::limpar( $item, $profundidade + 1 );
		}
		return $limpo;
	}

	/**
	 * Grava uma meta preservando barras invertidas: update_post_meta() aplica
	 * wp_unslash() ao valor, o que corromperia o JSON (\" e \n) e textos com "\".
	 *
	 * @param int        $post_id ID da inscrição.
	 * @param string     $chave   Nome da meta.
	 * @param string|int $valor   Valor.
	 */
	private static function meta( int $post_id, string $chave, $valor ): void {
		update_post_meta( $post_id, $chave, wp_slash( $valor ) );
	}

	/**
	 * Texto de uma linha, aceitando só valores escalares.
	 *
	 * @param mixed $valor Valor recebido.
	 */
	private static function texto( $valor ): string {
		return is_scalar( $valor ) ? sanitize_text_field( (string) $valor ) : '';
	}

	/**
	 * JSON no formato usado pelas metas (sem escapar Unicode e barras).
	 *
	 * @param mixed $valor Valor a codificar.
	 */
	private static function json( $valor ): string {
		return (string) wp_json_encode( $valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}
}
