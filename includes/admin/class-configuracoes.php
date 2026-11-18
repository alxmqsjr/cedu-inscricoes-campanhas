<?php
/**
 * Tela de Configurações: recebimento, chave da API, edição, autor e imagem padrão.
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;

/**
 * Configurações (Settings API), recebimento e gerenciamento da chave.
 */
final class Configuracoes {

	private const GRUPO             = 'cedu_inscricoes_configuracoes';
	private const NONCE_CHAVE       = 'cedu_inscricoes_chave';
	private const NONCE_RECEBIMENTO = 'cedu_inscricoes_recebimento';

	/**
	 * Chave recém-gerada, exibida uma única vez nesta resposta (nunca gravada).
	 *
	 * @var string
	 */
	private static string $chave_gerada = '';

	/**
	 * Mensagem do processamento da chave: [tipo, texto].
	 *
	 * @var string[]
	 */
	private static array $mensagem = array();

	/** Registra os hooks. */
	public static function registrar(): void {
		add_action( 'admin_init', array( self::class, 'registrar_campos' ) );
	}

	/** Registra as options e os campos. */
	public static function registrar_campos(): void {
		register_setting(
			self::GRUPO,
			Config::OPCAO_MENSAGEM_FECHADO,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);
		register_setting(
			self::GRUPO,
			Config::OPCAO_EDICAO,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitizar_edicao' ),
				'default'           => Config::EDICAO_PADRAO,
			)
		);
		register_setting(
			self::GRUPO,
			Config::OPCAO_AUTOR,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( self::class, 'sanitizar_autor' ),
				'default'           => Config::AUTOR_PADRAO,
			)
		);
		register_setting(
			self::GRUPO,
			Config::OPCAO_IMAGEM_URL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( self::class, 'sanitizar_url' ),
				'default'           => Config::IMAGEM_URL_PADRAO,
			)
		);

		add_settings_section( 'cedu_inscricoes_importacao', __( 'Importação', 'cedu-inscricoes-campanhas' ), '__return_null', Admin::PAGINA_CONFIGURACOES );

		add_settings_field( Config::OPCAO_MENSAGEM_FECHADO, __( 'Mensagem com o recebimento fechado', 'cedu-inscricoes-campanhas' ), array( self::class, 'campo_mensagem' ), Admin::PAGINA_CONFIGURACOES, 'cedu_inscricoes_importacao', array( 'label_for' => Config::OPCAO_MENSAGEM_FECHADO ) );
		add_settings_field( Config::OPCAO_EDICAO, __( 'Edição das inscrições novas', 'cedu-inscricoes-campanhas' ), array( self::class, 'campo_edicao' ), Admin::PAGINA_CONFIGURACOES, 'cedu_inscricoes_importacao', array( 'label_for' => Config::OPCAO_EDICAO ) );
		add_settings_field( Config::OPCAO_AUTOR, __( 'Autor das inscrições', 'cedu-inscricoes-campanhas' ), array( self::class, 'campo_autor' ), Admin::PAGINA_CONFIGURACOES, 'cedu_inscricoes_importacao', array( 'label_for' => Config::OPCAO_AUTOR ) );
		add_settings_field( Config::OPCAO_IMAGEM_URL, __( 'Imagem padrão', 'cedu-inscricoes-campanhas' ), array( self::class, 'campo_imagem' ), Admin::PAGINA_CONFIGURACOES, 'cedu_inscricoes_importacao', array( 'label_for' => Config::OPCAO_IMAGEM_URL ) );
	}

	/**
	 * Valida a URL da imagem padrão.
	 *
	 * @param mixed $valor Valor enviado.
	 */
	public static function sanitizar_url( $valor ): string {
		$url = esc_url_raw( is_string( $valor ) ? trim( $valor ) : '', array( 'http', 'https' ) );
		return '' !== $url ? $url : Config::IMAGEM_URL_PADRAO;
	}

	/**
	 * Aceita só um termo de edição existente (ou vazio, para não atribuir).
	 *
	 * @param mixed $valor Valor enviado.
	 */
	public static function sanitizar_edicao( $valor ): string {
		$slug = sanitize_title( is_string( $valor ) ? $valor : '' );
		return ( '' === $slug || get_term_by( 'slug', $slug, Esquema::TAX_EDICAO ) ) ? $slug : Config::edicao();
	}

	/**
	 * Aceita só um administrador.
	 *
	 * @param mixed $valor Valor enviado.
	 */
	public static function sanitizar_autor( $valor ): int {
		$id = absint( $valor );
		if ( Config::eh_administrador( $id ) ) {
			return $id;
		}
		add_settings_error( Config::OPCAO_AUTOR, 'autor_invalido', __( 'O autor das inscrições precisa ser um administrador. O valor anterior foi mantido.', 'cedu-inscricoes-campanhas' ) );
		return Config::autor();
	}

	/**
	 * Trata os botões da tela (load-*, antes de imprimir): abrir/fechar o
	 * recebimento e definir/gerar a chave. A chave gerada só aparece nesta
	 * resposta; no banco fica só o hash.
	 */
	public static function processar_acoes(): void {
		if ( 'post' !== sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- o nonce é conferido antes de qualquer ação.
		if ( ! empty( $_POST['cedu_inscricoes_acao_recebimento'] ) ) {
			Admin::exigir_permissao();
			check_admin_referer( self::NONCE_RECEBIMENTO );
			$abrir = 'abrir' === sanitize_key( wp_unslash( $_POST['cedu_inscricoes_acao_recebimento'] ) );
			update_option( Config::OPCAO_RECEBIMENTO, $abrir ? '1' : '0', false );
			self::$mensagem = $abrir
				? array( 'success', __( 'Recebimento aberto: a API voltou a aceitar envios.', 'cedu-inscricoes-campanhas' ) )
				: array( 'warning', __( 'Recebimento fechado: a API está recusando todos os envios.', 'cedu-inscricoes-campanhas' ) );
			return;
		}

		if ( empty( $_POST['cedu_inscricoes_acao_chave'] ) ) {
			return;
		}
		// phpcs:enable

		Admin::exigir_permissao();
		check_admin_referer( self::NONCE_CHAVE );

		$acao = sanitize_key( wp_unslash( $_POST['cedu_inscricoes_acao_chave'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- conferido acima.

		// Só gera e mostra uma chave para colar no wp-config.php; nada é salvo.
		if ( 'sugerir' === $acao ) {
			self::$chave_gerada = wp_generate_password( 48, false, false );
			self::$mensagem     = array( 'info', __( 'Chave gerada só para copiar: nada foi salvo. Cole a linha abaixo no wp-config.php e passe a mesma chave a quem mantém o formulário. A chave atual continua valendo até o wp-config.php ser alterado.', 'cedu-inscricoes-campanhas' ) );
			return;
		}

		if ( 'constante' === Autenticacao::origem() ) {
			self::$mensagem = array( 'error', __( 'A chave está definida no wp-config.php e não pode ser trocada por aqui.', 'cedu-inscricoes-campanhas' ) );
			return;
		}

		if ( 'gerar' === $acao ) {
			self::$chave_gerada = Autenticacao::gerar();
			self::$mensagem     = array( 'success', __( 'Nova chave gerada e já em uso. Copie agora: ela não será mostrada de novo. A chave anterior deixou de valer. Recomendado: cole também a linha abaixo no wp-config.php.', 'cedu-inscricoes-campanhas' ) );
			return;
		}

		if ( 'definir' === $acao ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- chave é segredo opaco; só o hash é gravado.
			$chave          = trim( (string) wp_unslash( $_POST['cedu_inscricoes_chave'] ?? '' ) );
			self::$mensagem = Autenticacao::salvar( $chave )
				? array( 'success', __( 'Chave salva. A chave anterior deixou de valer.', 'cedu-inscricoes-campanhas' ) )
				/* translators: %d: tamanho mínimo. */
				: array( 'error', sprintf( __( 'A chave precisa ter ao menos %d caracteres.', 'cedu-inscricoes-campanhas' ), Autenticacao::TAMANHO_MINIMO ) );
		}
	}

	/**
	 * Linha para o wp-config.php.
	 *
	 * @param string $chave Valor da chave ou um marcador.
	 */
	public static function linha_wp_config( string $chave ): string {
		return "define( '" . Config::CONSTANTE_CHAVE . "', '" . $chave . "' );";
	}

	/** Imprime a tela. */
	public static function pagina(): void {
		Admin::exigir_permissao();
		require DIR . '/templates/admin/pagina-configuracoes.php';
	}

	/** Mensagem do processamento da chave ([tipo, texto] ou vazio). */
	public static function mensagem(): array {
		return self::$mensagem;
	}

	/** Chave recém-gerada ('' se nenhuma). */
	public static function chave_gerada(): string {
		return self::$chave_gerada;
	}

	/** Ação do nonce da chave. */
	public static function nonce_chave(): string {
		return self::NONCE_CHAVE;
	}

	/** Ação do nonce do recebimento. */
	public static function nonce_recebimento(): string {
		return self::NONCE_RECEBIMENTO;
	}

	/** Campo: mensagem com o recebimento fechado. */
	public static function campo_mensagem(): void {
		printf(
			'<input type="text" class="large-text" name="%1$s" id="%1$s" value="%2$s" placeholder="%3$s">',
			esc_attr( Config::OPCAO_MENSAGEM_FECHADO ),
			esc_attr( (string) get_option( Config::OPCAO_MENSAGEM_FECHADO, '' ) ),
			esc_attr__( 'O recebimento de inscrições está encerrado.', 'cedu-inscricoes-campanhas' )
		);
		printf( '<p class="description">%s</p>', esc_html__( 'Devolvida ao formulário (HTTP 403) enquanto o recebimento estiver fechado. Vazio usa o texto padrão.', 'cedu-inscricoes-campanhas' ) );
	}

	/** Grupo da Settings API. */
	public static function grupo(): string {
		return self::GRUPO;
	}

	/** Campo: edição. */
	public static function campo_edicao(): void {
		$atual  = Config::edicao();
		$termos = taxonomy_exists( Esquema::TAX_EDICAO )
			? get_terms(
				array(
					'taxonomy'   => Esquema::TAX_EDICAO,
					'hide_empty' => false,
				)
			)
			: array();

		printf( '<select name="%1$s" id="%1$s">', esc_attr( Config::OPCAO_EDICAO ) );
		printf( '<option value="">%s</option>', esc_html__( '— não atribuir —', 'cedu-inscricoes-campanhas' ) );
		foreach ( is_array( $termos ) ? $termos : array() as $termo ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $termo->slug ), selected( $atual, $termo->slug, false ), esc_html( $termo->name ) );
		}
		echo '</select>';
		printf( '<p class="description">%s</p>', esc_html__( 'Termo de "Edição" atribuído a cada inscrição recebida que ainda não tenha edição. O Portal de Resultados filtra por ele.', 'cedu-inscricoes-campanhas' ) );
	}

	/** Campo: autor. */
	public static function campo_autor(): void {
		wp_dropdown_users(
			array(
				'name'     => Config::OPCAO_AUTOR,
				'id'       => Config::OPCAO_AUTOR,
				'selected' => Config::autor(),
				'role__in' => array( 'administrator' ),
			)
		);
		printf( '<p class="description">%s</p>', esc_html__( 'Somente administradores. Gravado como autor das inscrições criadas pela API e das mídias baixadas por ela. Se o usuário escolhido deixar de ser administrador, o plugin usa o administrador mais antigo.', 'cedu-inscricoes-campanhas' ) );
	}

	/** Campo: imagem padrão. */
	public static function campo_imagem(): void {
		printf(
			'<input type="url" class="large-text code" name="%1$s" id="%1$s" value="%2$s">',
			esc_attr( Config::OPCAO_IMAGEM_URL ),
			esc_attr( Config::imagem_padrao_url() )
		);
		printf( '<p class="description">%s</p>', esc_html__( 'Capa usada quando a inscrição não autoriza expor as imagens (campo e valor definidos em config/campos.php, papel "protecao"). É baixada uma única vez e compartilhada por todas essas inscrições; se a URL mudar, a nova imagem é baixada no próximo envio.', 'cedu-inscricoes-campanhas' ) );

		$id = (int) get_option( Config::OPCAO_IMAGEM_ID, 0 );
		if ( $id && Midia::eh_imagem_padrao( $id ) ) {
			$url = wp_get_attachment_image_url( $id, 'thumbnail' );
			if ( $url ) {
				printf(
					'<a href="%s"><img class="icamp-imagem-padrao" src="%s" alt="%s"></a>',
					esc_url( (string) get_edit_post_link( $id ) ),
					esc_url( $url ),
					esc_attr__( 'Imagem padrão atual', 'cedu-inscricoes-campanhas' )
				);
			}
		}
	}
}
