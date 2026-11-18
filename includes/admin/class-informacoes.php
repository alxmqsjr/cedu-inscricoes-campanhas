<?php
/**
 * Tela "Informações": situação, formato do JSON e tutorial para novas edições.
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;

/**
 * Tela de informações e tutorial.
 */
final class Informacoes {

	private const NONCE_LIMPAR = 'cedu_inscricoes_limpar_ignorados';

	/** Trata o botão "limpar a lista de campos ignorados" (load-*). */
	public static function processar_acoes(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- o nonce é conferido logo abaixo.
		if ( 'post' !== sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) || empty( $_POST['cedu_inscricoes_limpar_ignorados'] ) ) {
			return;
		}
		Admin::exigir_permissao();
		check_admin_referer( self::NONCE_LIMPAR );
		delete_option( Config::OPCAO_CAMPOS_IGNORADOS );
		wp_safe_redirect( Admin::url( Admin::PAGINA_INFORMACOES, array( 'limpo' => '1' ) ) . '#campos-ignorados' );
		exit;
	}

	/** Imprime a tela. */
	public static function pagina(): void {
		Admin::exigir_permissao();
		require DIR . '/templates/admin/pagina-informacoes.php';
	}

	/** Ação do nonce de limpeza. */
	public static function nonce_limpar(): string {
		return self::NONCE_LIMPAR;
	}

	/**
	 * Campos recebidos e não configurados.
	 *
	 * @return array<string, array{tipo: string, vezes: int, ultima: int}>
	 */
	public static function ignorados(): array {
		$lista = get_option( Config::OPCAO_CAMPOS_IGNORADOS, array() );
		return is_array( $lista ) ? $lista : array();
	}

	/**
	 * Linhas da tabela de campos: [campo no JSON, tipo, meta, observação].
	 *
	 * @return array<int, array{0: string, 1: string, 2: string, 3: string}>
	 */
	public static function tabela_campos(): array {
		$linhas = array();
		$id     = Esquema::papel( 'id' );
		$envio  = Esquema::papel( 'enviado_em' );
		$prot   = Esquema::papel( 'protecao' );

		if ( '' !== ( $id['campo'] ?? '' ) ) {
			$linhas[] = array( $id['campo'], __( 'texto', 'cedu-inscricoes-campanhas' ), $id['meta'], __( 'Identificador único do envio. Um reenvio com o mesmo valor atualiza a inscrição.', 'cedu-inscricoes-campanhas' ) );
		}
		if ( '' !== Esquema::campo( 'titulo' ) ) {
			$linhas[] = array( Esquema::campo( 'titulo' ), __( 'texto', 'cedu-inscricoes-campanhas' ), __( '(título do post)', 'cedu-inscricoes-campanhas' ), __( 'Vira o título da inscrição.', 'cedu-inscricoes-campanhas' ) );
		}
		if ( '' !== ( $envio['campo'] ?? '' ) ) {
			$campo    = $envio['campo'] . ( '' !== $envio['subcampo'] ? '.' . $envio['subcampo'] : '' );
			$linhas[] = array( $campo, __( 'número', 'cedu-inscricoes-campanhas' ), $envio['meta'], __( 'Data do envio, em segundos (timestamp).', 'cedu-inscricoes-campanhas' ) );
		}

		$tipos = array(
			'texto'     => __( 'texto', 'cedu-inscricoes-campanhas' ),
			'numero'    => __( 'número', 'cedu-inscricoes-campanhas' ),
			'lista'     => __( 'lista (JSON)', 'cedu-inscricoes-campanhas' ),
			'objeto'    => __( 'objeto (JSON)', 'cedu-inscricoes-campanhas' ),
			'evidencia' => __( 'evidências (JSON)', 'cedu-inscricoes-campanhas' ),
		);
		foreach ( $tipos as $tipo => $rotulo ) {
			foreach ( Esquema::campos( $tipo ) as $campo ) {
				$obs = '';
				if ( Esquema::campo( 'capa' ) === $campo ) {
					$obs = __( 'URL da capa: a imagem é baixada e vira a imagem de destaque.', 'cedu-inscricoes-campanhas' );
				} elseif ( ( $prot['campo'] ?? '' ) === $campo ) {
					/* translators: %s: valor que oculta as imagens. */
					$obs = sprintf( __( 'Com o valor "%s", a capa é a imagem padrão e as imagens do participante não viram capa.', 'cedu-inscricoes-campanhas' ), $prot['oculta'] );
				} elseif ( 'evidencia' === $tipo ) {
					$obs = __( 'Lista de {url, caption}. As imagens não são baixadas.', 'cedu-inscricoes-campanhas' );
				}
				$linhas[] = array( $campo, $rotulo, $campo, $obs );
			}
		}

		$n = Esquema::total_imagens_publicas();
		if ( $n > 0 ) {
			$faixa    = 1 === $n ? Esquema::meta_imagem_publica( 1 ) : Esquema::meta_imagem_publica( 1 ) . ' … ' . Esquema::meta_imagem_publica( $n );
			$linhas[] = array( $faixa, __( 'imagem pública', 'cedu-inscricoes-campanhas' ), $faixa, __( 'Objeto {url, caption, source, sourceIndex}. A imagem é baixada para a Biblioteca de Mídia; a primeira vira capa se não houver outra.', 'cedu-inscricoes-campanhas' ) );
		}

		foreach ( Esquema::apelidos() as $novo => $configurado ) {
			/* translators: %s: nome configurado. */
			$linhas[] = array( $novo, __( 'apelido', 'cedu-inscricoes-campanhas' ), $configurado, sprintf( __( 'Tratado como "%s".', 'cedu-inscricoes-campanhas' ), $configurado ) );
		}

		return $linhas;
	}

	/** JSON de exemplo, montado a partir de config/campos.php. */
	public static function exemplo_json(): string {
		$ex   = array();
		$id   = Esquema::papel( 'id' );
		$prot = Esquema::papel( 'protecao' );

		if ( '' !== ( $id['campo'] ?? '' ) ) {
			$ex[ $id['campo'] ] = 'abc123';
		}
		if ( '' !== Esquema::campo( 'titulo' ) ) {
			$ex[ Esquema::campo( 'titulo' ) ] = 'Nome da campanha';
		}

		$amostra = array_slice( Esquema::campos( 'texto' ), 0, 3 );
		foreach ( $amostra as $campo ) {
			$ex[ $campo ] = '…';
		}
		if ( '' !== Esquema::campo( 'capa' ) ) {
			$ex[ Esquema::campo( 'capa' ) ] = 'https://exemplo.org/capa.jpg';
		}
		if ( '' !== ( $prot['campo'] ?? '' ) ) {
			$ex[ $prot['campo'] ] = 'opcao1';
		}
		foreach ( array_slice( Esquema::campos( 'numero' ), 0, 1 ) as $campo ) {
			$ex[ $campo ] = 87;
		}
		foreach ( array_slice( Esquema::campos( 'lista' ), 0, 1 ) as $campo ) {
			$ex[ $campo ] = array( 'item 1', 'item 2' );
		}
		foreach ( array_slice( Esquema::campos( 'evidencia' ), 0, 1 ) as $campo ) {
			$ex[ $campo ] = array(
				array(
					'url'     => 'https://exemplo.org/foto.jpg',
					'caption' => 'Legenda',
				),
			);
			if ( Esquema::total_imagens_publicas() > 0 ) {
				$ex[ Esquema::meta_imagem_publica( 1 ) ] = array(
					'url'         => 'https://exemplo.org/foto.jpg',
					'caption'     => 'Legenda pública',
					'source'      => $campo,
					'sourceIndex' => 0,
				);
			}
		}
		$envio = Esquema::papel( 'enviado_em' );
		if ( '' !== ( $envio['campo'] ?? '' ) ) {
			$ex[ $envio['campo'] ] = '' !== $envio['subcampo'] ? array( $envio['subcampo'] => 1760000000 ) : 1760000000;
		}

		return (string) wp_json_encode( $ex, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}
}
