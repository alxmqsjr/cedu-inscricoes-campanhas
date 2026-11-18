<?php
/**
 * Menus, telas e recursos do painel.
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;

/**
 * Telas do gerenciador.
 */
final class Admin {

	public const PAGINA_GERENCIAR     = 'cedu-inscricoes-gerenciar';
	public const PAGINA_MIDIAS        = 'cedu-inscricoes-midias';
	public const PAGINA_CONFIGURACOES = 'cedu-inscricoes-configuracoes';
	public const PAGINA_INFORMACOES   = 'cedu-inscricoes-informacoes';

	/** Opção de tela com itens por página. */
	public const OPCAO_POR_PAGINA = 'cedu_inscricoes_por_pagina';

	/** Capacidade exigida em todas as telas. */
	public const CAPACIDADE = 'manage_options';

	/**
	 * Hooks das telas criadas (para carregar CSS/JS só nelas).
	 *
	 * @var string[]
	 */
	private static array $telas = array();

	/**
	 * Tabela da tela atual (montada no load-*, antes de imprimir).
	 *
	 * @var Tabela_Inscricoes|Tabela_Midias|null
	 */
	private static ?\WP_List_Table $tabela = null;

	/** Registra os hooks. */
	public static function registrar(): void {
		add_action( 'admin_menu', array( self::class, 'menus' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'recursos' ) );
		add_action( 'admin_notices', array( self::class, 'aviso_chave' ) );
		add_filter( 'set-screen-option', array( self::class, 'salvar_opcao_tela' ), 10, 3 );
		add_filter( 'plugin_action_links_' . plugin_basename( ARQUIVO ), array( self::class, 'links_plugin' ) );
	}

	/** Submenus em "Inscrições". */
	public static function menus(): void {
		$pai = 'edit.php?post_type=' . Esquema::POST_TYPE;

		$gerenciar = add_submenu_page(
			$pai,
			__( 'Gerenciar inscrições', 'cedu-inscricoes-campanhas' ),
			__( 'Gerenciar', 'cedu-inscricoes-campanhas' ),
			self::CAPACIDADE,
			self::PAGINA_GERENCIAR,
			array( self::class, 'pagina_gerenciar' )
		);

		$midias = add_submenu_page(
			$pai,
			__( 'Mídias das inscrições', 'cedu-inscricoes-campanhas' ),
			__( 'Mídias', 'cedu-inscricoes-campanhas' ),
			self::CAPACIDADE,
			self::PAGINA_MIDIAS,
			array( self::class, 'pagina_midias' )
		);

		$config = add_submenu_page(
			$pai,
			__( 'Configurações das inscrições', 'cedu-inscricoes-campanhas' ),
			__( 'Configurações', 'cedu-inscricoes-campanhas' ),
			self::CAPACIDADE,
			self::PAGINA_CONFIGURACOES,
			array( Configuracoes::class, 'pagina' )
		);

		$info = add_submenu_page(
			$pai,
			__( 'Informações e tutorial', 'cedu-inscricoes-campanhas' ),
			__( 'Informações', 'cedu-inscricoes-campanhas' ),
			self::CAPACIDADE,
			self::PAGINA_INFORMACOES,
			array( Informacoes::class, 'pagina' )
		);

		self::$telas = array_filter( array( $gerenciar, $midias, $config, $info ) );

		if ( $info ) {
			add_action( "load-{$info}", array( Informacoes::class, 'processar_acoes' ) );
		}

		if ( $gerenciar ) {
			add_action( "load-{$gerenciar}", array( self::class, 'carregar_gerenciar' ) );
		}
		if ( $midias ) {
			add_action( "load-{$midias}", array( self::class, 'carregar_midias' ) );
		}
		if ( $config ) {
			add_action( "load-{$config}", array( Configuracoes::class, 'processar_acoes' ) );
		}
	}

	/** Prepara a tela de inscrições (opção de tela + tabela). */
	public static function carregar_gerenciar(): void {
		add_screen_option(
			'per_page',
			array(
				'default' => 50,
				'option'  => self::OPCAO_POR_PAGINA,
			)
		);
		self::$tabela = new Tabela_Inscricoes();
	}

	/** Prepara a tela de mídias. */
	public static function carregar_midias(): void {
		add_screen_option(
			'per_page',
			array(
				'default' => 50,
				'option'  => self::OPCAO_POR_PAGINA,
			)
		);
		self::$tabela = new Tabela_Midias();
	}

	/**
	 * Aceita a opção de itens por página.
	 *
	 * @param mixed  $status Valor atual.
	 * @param string $opcao  Nome da opção.
	 * @param mixed  $valor  Valor enviado.
	 * @return mixed
	 */
	public static function salvar_opcao_tela( $status, $opcao, $valor ) {
		return self::OPCAO_POR_PAGINA === $opcao ? min( 500, max( 1, (int) $valor ) ) : $status;
	}

	/** Tela "Gerenciar". */
	public static function pagina_gerenciar(): void {
		self::exigir_permissao();
		$tabela = self::$tabela instanceof Tabela_Inscricoes ? self::$tabela : new Tabela_Inscricoes();
		$tabela->prepare_items();
		require DIR . '/templates/admin/pagina-gerenciar.php';
	}

	/** Tela "Mídias". */
	public static function pagina_midias(): void {
		self::exigir_permissao();
		$tabela = self::$tabela instanceof Tabela_Midias ? self::$tabela : new Tabela_Midias();
		$tabela->prepare_items();
		require DIR . '/templates/admin/pagina-midias.php';
	}

	/**
	 * CSS e JS só nas telas do plugin.
	 *
	 * @param string $hook Hook da tela atual.
	 */
	public static function recursos( string $hook ): void {
		if ( ! in_array( $hook, self::$telas, true ) ) {
			return;
		}

		$url = plugin_dir_url( ARQUIVO ) . 'assets/admin/';
		wp_enqueue_style( 'icamp-admin', $url . 'gerenciador.css', array(), VERSAO );

		if ( ! self::$tabela instanceof Tabela_Inscricoes ) {
			return;
		}

		wp_enqueue_script( 'icamp-gerenciador', $url . 'gerenciador.js', array(), VERSAO, true );
		wp_localize_script(
			'icamp-gerenciador',
			'icampGerenciador',
			array(
				'rest'    => esc_url_raw( rest_url( Rest_Admin::NAMESPACE . '/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'lote'    => 5,
				'filtros' => self::$tabela->filtros(),
				'total'   => Repositorio::contar( self::$tabela->filtros() ),
				'textos'  => array(
					/* translators: %d: quantidade. */
					'confirmarSelecionadas' => __( 'Apagar %d inscrição(ões) e toda a mídia delas? Não há como desfazer.', 'cedu-inscricoes-campanhas' ),
					/* translators: %d: quantidade. */
					'confirmarTodas'        => __( "Isto apaga as %d inscrições da lista atual (com os filtros aplicados), suas metas e sua mídia, sem recuperação.\n\nDigite APAGAR TUDO para confirmar:", 'cedu-inscricoes-campanhas' ),
					'fraseConfirmacao'      => 'APAGAR TUDO',
					'fraseIncorreta'        => __( 'Texto de confirmação incorreto. Nada foi apagado.', 'cedu-inscricoes-campanhas' ),
					'nenhumaSelecionada'    => __( 'Selecione ao menos uma inscrição.', 'cedu-inscricoes-campanhas' ),
					'apagando'              => __( 'Apagando…', 'cedu-inscricoes-campanhas' ),
					/* translators: 1: processadas, 2: total. */
					'progresso'             => __( 'Processadas: %1$d de %2$d', 'cedu-inscricoes-campanhas' ),
					/* translators: 1: inscrições apagadas, 2: mídias apagadas. */
					'concluido'             => __( 'Concluído: %1$d inscrição(ões) e %2$d mídia(s) apagadas. Atualizando a lista…', 'cedu-inscricoes-campanhas' ),
					'erro'                  => __( 'Processo interrompido:', 'cedu-inscricoes-campanhas' ),
				),
			)
		);
	}

	/** Aviso quando a API está sem chave. */
	public static function aviso_chave(): void {
		if ( ! current_user_can( self::CAPACIDADE ) ) {
			return;
		}

		$tela = get_current_screen();
		if ( ! $tela || ! ( 'plugins' === $tela->id || Esquema::POST_TYPE === $tela->post_type ) ) {
			return;
		}

		// Na própria tela de Configurações o estado já aparece em destaque.
		if ( str_ends_with( (string) $tela->id, self::PAGINA_CONFIGURACOES ) ) {
			return;
		}

		$avisos = array();
		if ( ! Autenticacao::configurada() ) {
			$avisos[] = __( 'a API está recusando envios porque nenhuma chave foi configurada.', 'cedu-inscricoes-campanhas' );
		}
		if ( ! Config::recebimento_aberto() ) {
			$avisos[] = __( 'o recebimento de envios está fechado.', 'cedu-inscricoes-campanhas' );
		}

		foreach ( $avisos as $aviso ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
				esc_html__( 'Inscrições de Campanhas:', 'cedu-inscricoes-campanhas' ),
				esc_html( $aviso ),
				esc_url( self::url( self::PAGINA_CONFIGURACOES ) ),
				esc_html__( 'Ver configurações', 'cedu-inscricoes-campanhas' )
			);
		}
	}

	/**
	 * Link "Configurações" na lista de plugins.
	 *
	 * @param string[] $links Links atuais.
	 * @return string[]
	 */
	public static function links_plugin( array $links ): array {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( self::url( self::PAGINA_CONFIGURACOES ) ), esc_html__( 'Configurações', 'cedu-inscricoes-campanhas' ) ),
			sprintf( '<a href="%s">%s</a>', esc_url( self::url( self::PAGINA_INFORMACOES ) ), esc_html__( 'Informações', 'cedu-inscricoes-campanhas' ) )
		);
		return $links;
	}

	/**
	 * URL de uma tela do plugin.
	 *
	 * @param string               $pagina Slug da tela.
	 * @param array<string, mixed> $args   Parâmetros extras.
	 */
	public static function url( string $pagina, array $args = array() ): string {
		return add_query_arg(
			array_merge(
				array(
					'post_type' => Esquema::POST_TYPE,
					'page'      => $pagina,
				),
				$args
			),
			admin_url( 'edit.php' )
		);
	}

	/** Interrompe se o usuário não puder ver as telas. */
	public static function exigir_permissao(): void {
		if ( ! current_user_can( self::CAPACIDADE ) ) {
			wp_die( esc_html__( 'Você não tem permissão para acessar esta página.', 'cedu-inscricoes-campanhas' ), 403 );
		}
	}
}
