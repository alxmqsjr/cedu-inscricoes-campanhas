<?php
/**
 * Tela "Mídias das inscrições" (somente consulta).
 *
 * @package CemadenInscricoes
 *
 * @var \CemadenInscricoes\Tabela_Midias $tabela
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;

$cedu_inscricoes_origens = Tabela_Midias::origens();
?>
<div class="wrap icamp">
	<h1><?php esc_html_e( 'Mídias das inscrições', 'cedu-inscricoes-campanhas' ); ?></h1>

	<p class="description">
		<?php echo esc_html( $cedu_inscricoes_origens[ $tabela->origem() ][1] ); ?>
		<?php esc_html_e( 'Esta tela é só para consulta: a mídia de uma inscrição é apagada junto com ela, em "Gerenciar".', 'cedu-inscricoes-campanhas' ); ?>
	</p>

	<?php $tabela->views(); ?>

	<form method="get">
		<input type="hidden" name="post_type" value="<?php echo esc_attr( Esquema::POST_TYPE ); ?>">
		<input type="hidden" name="page" value="<?php echo esc_attr( Admin::PAGINA_MIDIAS ); ?>">
		<input type="hidden" name="origem" value="<?php echo esc_attr( $tabela->origem() ); ?>">
		<?php $tabela->display(); ?>
	</form>
</div>
