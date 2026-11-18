<?php
/**
 * Guia dos campos (docs/CAMPOS.md) exibido no painel.
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap icamp icamp-info icamp-doc">
	<p><a href="<?php echo esc_url( Admin::url( Admin::PAGINA_INFORMACOES ) ); ?>">&larr; <?php esc_html_e( 'Voltar para Informações', 'cedu-inscricoes-campanhas' ); ?></a></p>
	<h1><?php esc_html_e( 'Guia dos campos do formulário', 'cedu-inscricoes-campanhas' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Conteúdo do arquivo docs/CAMPOS.md desta versão do plugin.', 'cedu-inscricoes-campanhas' ); ?></p>
	<?php echo Informacoes::html_doc(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gerado por Markdown::html(), que escapa todo o texto. ?>
</div>
