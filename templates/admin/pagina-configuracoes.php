<?php
/**
 * Tela "Configurações das inscrições".
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;

$cedu_inscricoes_origem   = Autenticacao::origem();
$cedu_inscricoes_mensagem = Configuracoes::mensagem();
$cedu_inscricoes_gerada   = Configuracoes::chave_gerada();
$cedu_inscricoes_aberto   = Config::recebimento_aberto();
$cedu_inscricoes_rotulos  = array(
	'constante' => __( 'Em uso: a constante CEDU_INSCRICOES_API_KEY do wp-config.php.', 'cedu-inscricoes-campanhas' ),
	'opcao'     => __( 'Em uso: a chave salva nesta tela (o banco guarda só o hash). Recomendado: passar para o wp-config.php.', 'cedu-inscricoes-campanhas' ),
	''          => __( 'Nenhuma chave configurada: a API está recusando todos os envios.', 'cedu-inscricoes-campanhas' ),
);
?>
<div class="wrap icamp">
	<h1><?php esc_html_e( 'Configurações das inscrições', 'cedu-inscricoes-campanhas' ); ?></h1>

	<?php settings_errors(); ?>

	<?php if ( $cedu_inscricoes_mensagem ) : ?>
		<div class="notice notice-<?php echo esc_attr( $cedu_inscricoes_mensagem[0] ); ?>"><p><?php echo esc_html( $cedu_inscricoes_mensagem[1] ); ?></p></div>
	<?php endif; ?>

	<?php if ( '' !== $cedu_inscricoes_gerada ) : ?>
		<div class="icamp-chave-nova">
			<p><strong><?php esc_html_e( 'Chave gerada (copie agora, ela não será mostrada de novo):', 'cedu-inscricoes-campanhas' ); ?></strong></p>
			<p><code class="icamp-chave-gerada"><?php echo esc_html( $cedu_inscricoes_gerada ); ?></code></p>
			<p><strong><?php esc_html_e( 'Linha pronta para o wp-config.php:', 'cedu-inscricoes-campanhas' ); ?></strong></p>
			<pre class="icamp-chave-gerada"><?php echo esc_html( Configuracoes::linha_wp_config( $cedu_inscricoes_gerada ) ); ?></pre>
		</div>
	<?php endif; ?>

	<?php require DIR . '/templates/admin/parte-aviso-documentacao.php'; ?>

	<h2><?php esc_html_e( 'Recebimento de envios', 'cedu-inscricoes-campanhas' ); ?></h2>
	<form method="post" class="icamp-recebimento <?php echo $cedu_inscricoes_aberto ? 'icamp-aberto' : 'icamp-fechado'; ?>">
		<?php wp_nonce_field( Configuracoes::nonce_recebimento() ); ?>
		<p class="icamp-recebimento-estado">
			<span class="dashicons <?php echo $cedu_inscricoes_aberto ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>" aria-hidden="true"></span>
			<strong>
				<?php
				echo $cedu_inscricoes_aberto
					? esc_html__( 'Aberto: a API está aceitando inscrições.', 'cedu-inscricoes-campanhas' )
					: esc_html__( 'Fechado: a API está recusando todos os envios (HTTP 403) e nada é gravado.', 'cedu-inscricoes-campanhas' );
				?>
			</strong>
		</p>
		<?php if ( $cedu_inscricoes_aberto ) : ?>
			<button type="submit" class="button icamp-perigo" name="cedu_inscricoes_acao_recebimento" value="fechar" onclick="return confirm('<?php echo esc_js( __( 'Fechar o recebimento? O formulário passa a receber um erro em cada envio até você abrir de novo.', 'cedu-inscricoes-campanhas' ) ); ?>');"><?php esc_html_e( 'Fechar recebimento', 'cedu-inscricoes-campanhas' ); ?></button>
		<?php else : ?>
			<button type="submit" class="button button-primary" name="cedu_inscricoes_acao_recebimento" value="abrir"><?php esc_html_e( 'Abrir recebimento', 'cedu-inscricoes-campanhas' ); ?></button>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Afeta só a API do formulário. O gerenciamento no painel e o comando "wp inscricoes importar" continuam funcionando.', 'cedu-inscricoes-campanhas' ); ?></p>
	</form>

	<hr>

	<h2><?php esc_html_e( 'Chave da API', 'cedu-inscricoes-campanhas' ); ?></h2>
	<p>
		<?php
		printf(
			/* translators: 1: endpoint, 2: cabeçalho. */
			esc_html__( 'O formulário envia as inscrições para %1$s com a chave no cabeçalho %2$s.', 'cedu-inscricoes-campanhas' ),
			'<code>' . esc_html( rest_url( 'cedu/v1/inscricao' ) ) . '</code>',
			'<code>' . esc_html( Autenticacao::CABECALHO ) . '</code>'
		);
		?>
	</p>
	<p><strong><?php esc_html_e( 'Situação:', 'cedu-inscricoes-campanhas' ); ?></strong> <?php echo esc_html( $cedu_inscricoes_rotulos[ $cedu_inscricoes_origem ] ); ?></p>

	<h3><?php esc_html_e( 'Recomendado: chave no wp-config.php', 'cedu-inscricoes-campanhas' ); ?></h3>
	<?php require DIR . '/templates/admin/parte-wp-config.php'; ?>

	<form method="post" autocomplete="off">
		<?php wp_nonce_field( Configuracoes::nonce_chave() ); ?>
		<p>
			<button type="submit" class="button button-primary" name="cedu_inscricoes_acao_chave" value="sugerir"><?php esc_html_e( 'Gerar chave para o wp-config.php', 'cedu-inscricoes-campanhas' ); ?></button>
			<span class="description"><?php esc_html_e( 'Mostra uma chave nova e a linha pronta para copiar. Nada é salvo: a chave atual continua valendo até você alterar o wp-config.php.', 'cedu-inscricoes-campanhas' ); ?></span>
		</p>

		<?php if ( 'constante' !== $cedu_inscricoes_origem ) : ?>
			<h3><?php esc_html_e( 'Alternativa: chave salva nesta tela', 'cedu-inscricoes-campanhas' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Use só se não for possível editar o wp-config.php. Vale enquanto a constante não estiver definida.', 'cedu-inscricoes-campanhas' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="cedu_inscricoes_chave"><?php esc_html_e( 'Definir a chave', 'cedu-inscricoes-campanhas' ); ?></label></th>
					<td>
						<input type="password" class="regular-text code" name="cedu_inscricoes_chave" id="cedu_inscricoes_chave" autocomplete="new-password" spellcheck="false" minlength="<?php echo (int) Autenticacao::TAMANHO_MINIMO; ?>">
						<button type="submit" class="button" name="cedu_inscricoes_acao_chave" value="definir"><?php esc_html_e( 'Salvar chave', 'cedu-inscricoes-campanhas' ); ?></button>
						<p class="description">
							<?php
							printf(
								/* translators: %d: tamanho mínimo. */
								esc_html__( 'Para manter a chave que o formulário já usa (mínimo de %d caracteres). Ela não fica visível depois de salva.', 'cedu-inscricoes-campanhas' ),
								(int) Autenticacao::TAMANHO_MINIMO
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Gerar e salvar uma nova', 'cedu-inscricoes-campanhas' ); ?></th>
					<td>
						<button type="submit" class="button" name="cedu_inscricoes_acao_chave" value="gerar" onclick="return confirm('<?php echo esc_js( __( 'Gerar e salvar uma nova chave invalida a atual: o formulário só volta a enviar depois de atualizado. Continuar?', 'cedu-inscricoes-campanhas' ) ); ?>');"><?php esc_html_e( 'Gerar e salvar nova chave', 'cedu-inscricoes-campanhas' ); ?></button>
					</td>
				</tr>
			</table>
		<?php endif; ?>
	</form>

	<hr>

	<form method="post" action="options.php">
		<?php
		settings_fields( Configuracoes::grupo() );
		do_settings_sections( Admin::PAGINA_CONFIGURACOES );
		submit_button();
		?>
	</form>
</div>
