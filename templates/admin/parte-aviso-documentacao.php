<?php
/**
 * Aviso com o link da documentação (telas Informações e Configurações).
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;
?>
<div class="notice notice-info inline icamp-aviso-doc">
	<p>
		<strong><?php esc_html_e( 'O formulário mudou?', 'cedu-inscricoes-campanhas' ); ?></strong>
		<?php esc_html_e( 'Campos novos, removidos ou renomeados no JSON são configurados num único arquivo do plugin, config/campos.php. Faça a alteração no repositório do plugin, e não direto no servidor: uma atualização do plugin substitui o arquivo. Depois ajuste o tema (single-inscricoes.php) se o campo for exibido no site.', 'cedu-inscricoes-campanhas' ); ?>
	</p>
	<p>
		<a class="button" href="<?php echo esc_url( Informacoes::url_doc() ); ?>"><?php esc_html_e( 'Guia dos campos', 'cedu-inscricoes-campanhas' ); ?></a>
		<a class="button-link" href="<?php echo esc_url( Config::URL_REPOSITORIO ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Repositório do plugin (acesso restrito)', 'cedu-inscricoes-campanhas' ); ?></a>
	</p>
</div>
