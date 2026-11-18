<?php
/**
 * Inicialização, ativação e desativação.
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;

/**
 * Orquestra os módulos do plugin.
 */
final class Plugin {

	/** Registra os módulos. */
	public static function iniciar(): void {
		add_action(
			'init',
			static function () {
				load_plugin_textdomain( DOMINIO, false, dirname( plugin_basename( ARQUIVO ) ) . '/languages' );
			}
		);

		Post_Type::registrar();
		Rest_Controller::registrar();
		Rest_Admin::registrar();
		Atualizador::registrar();

		add_action( 'before_delete_post', array( self::class, 'antes_de_apagar' ), 10, 2 );

		if ( is_admin() ) {
			require_once DIR . '/includes/admin/class-tabela-inscricoes.php';
			require_once DIR . '/includes/admin/class-tabela-midias.php';
			require_once DIR . '/includes/admin/class-configuracoes.php';
			require_once DIR . '/includes/admin/class-markdown.php';
			require_once DIR . '/includes/admin/class-informacoes.php';
			require_once DIR . '/includes/admin/class-colunas.php';
			require_once DIR . '/includes/admin/class-admin.php';
			Admin::registrar();
			Colunas::registrar();
			Configuracoes::registrar();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once DIR . '/includes/class-cli.php';
			\WP_CLI::add_command( 'inscricoes', Cli::class );
		}
	}

	/**
	 * Ao apagar de vez uma inscrição (por este plugin ou pela tela nativa),
	 * apaga também a mídia dela, exceto a imagem padrão compartilhada.
	 * Precisa ser antes: depois, o WordPress desvincula os anexos do post.
	 *
	 * @param int           $post_id ID do post.
	 * @param \WP_Post|null $post    Post.
	 */
	public static function antes_de_apagar( int $post_id, $post = null ): void {
		$tipo = $post instanceof \WP_Post ? $post->post_type : get_post_type( $post_id );
		if ( Esquema::POST_TYPE === $tipo ) {
			Midia::apagar( Midia::anexos_da_inscricao( $post_id ) );
		}
	}

	/** Ativação: cria as options com os valores padrão. */
	public static function ativar(): void {
		add_option( Config::OPCAO_RECEBIMENTO, '1', '', false );
		add_option( Config::OPCAO_EDICAO, Config::EDICAO_PADRAO, '', false );
		add_option( Config::OPCAO_AUTOR, Config::autor(), '', false ); // Sempre um administrador.
		add_option( Config::OPCAO_IMAGEM_URL, Config::IMAGEM_URL_PADRAO, '', false );

		Post_Type::registrar_se_ausente();
		flush_rewrite_rules();
	}

	/** Desativação. */
	public static function desativar(): void {
		flush_rewrite_rules();
	}
}
