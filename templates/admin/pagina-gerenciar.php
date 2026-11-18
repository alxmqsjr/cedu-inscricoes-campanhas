<?php
/**
 * Tela "Gerenciar inscrições".
 *
 * @package CemadenInscricoes
 *
 * @var \CemadenInscricoes\Tabela_Inscricoes $tabela
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap icamp">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Gerenciar inscrições', 'cedu-inscricoes-campanhas' ); ?></h1>
	<hr class="wp-header-end">

	<div class="notice notice-warning inline icamp-aviso">
		<p>
			<?php esc_html_e( 'Apagar aqui é definitivo: sai o post, todas as metas, os termos e os arquivos de mídia da inscrição (capa e imagens públicas). Não passa pela lixeira. A imagem padrão compartilhada nunca é apagada.', 'cedu-inscricoes-campanhas' ); ?>
		</p>
	</div>

	<?php $tabela->views(); ?>

	<form method="get" id="icamp-form-inscricoes">
		<input type="hidden" name="post_type" value="<?php echo esc_attr( Esquema::POST_TYPE ); ?>">
		<input type="hidden" name="page" value="<?php echo esc_attr( Admin::PAGINA_GERENCIAR ); ?>">
		<?php if ( '' !== $tabela->filtros()['duplicadas'] ) : ?>
			<input type="hidden" name="duplicadas" value="1">
		<?php endif; ?>

		<?php $tabela->search_box( __( 'Buscar inscrições', 'cedu-inscricoes-campanhas' ), 'icamp-busca' ); ?>
		<?php $tabela->display(); ?>
	</form>

	<div id="icamp-progresso" class="icamp-progresso" hidden>
		<div class="icamp-barra"><div class="icamp-barra-preenchida"></div></div>
		<p class="icamp-status" role="status" aria-live="polite"></p>
		<ul class="icamp-log"></ul>
	</div>

	<noscript>
		<div class="notice notice-error inline"><p><?php esc_html_e( 'A exclusão depende de JavaScript.', 'cedu-inscricoes-campanhas' ); ?></p></div>
	</noscript>
</div>
