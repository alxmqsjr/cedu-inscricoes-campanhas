<?php
/**
 * Bloco de destaque: chave da API no wp-config.php (Configurações e Informações).
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;

$cedu_inscricoes_na_constante = 'constante' === Autenticacao::origem();
?>
<div class="icamp-wp-config <?php echo $cedu_inscricoes_na_constante ? 'icamp-aberto' : 'icamp-fechado'; ?>">
	<p class="icamp-recebimento-estado">
		<span class="dashicons <?php echo $cedu_inscricoes_na_constante ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
		<strong>
			<?php
			echo $cedu_inscricoes_na_constante
				? esc_html__( 'A constante CEDU_INSCRICOES_API_KEY foi encontrada no wp-config.php e é a chave em uso.', 'cedu-inscricoes-campanhas' )
				: esc_html__( 'A constante CEDU_INSCRICOES_API_KEY NÃO está no wp-config.php. Inclua-a (recomendado) e confira aqui depois.', 'cedu-inscricoes-campanhas' );
			?>
		</strong>
	</p>
	<p><?php esc_html_e( 'Acrescente esta linha ao wp-config.php, na raiz do site, antes do comentário "That\'s all, stop editing!" (ou "Isso é tudo, pode parar de editar!"):', 'cedu-inscricoes-campanhas' ); ?></p>
	<pre><?php echo esc_html( Configuracoes::linha_wp_config( 'COLE-A-CHAVE-AQUI' ) ); ?></pre>
	<ul>
		<li><?php esc_html_e( '"COLE-A-CHAVE-AQUI" não é uma variável: o WordPress não preenche nada sozinho. Substitua esse texto, à mão, pela chave em si, entre as aspas simples.', 'cedu-inscricoes-campanhas' ); ?></li>
		<li><?php esc_html_e( 'Use exatamente a mesma chave configurada no formulário externo. Para criar uma nova, use o botão "Gerar chave para o wp-config.php" em Configurações: ela aparece uma única vez, já na linha pronta para copiar.', 'cedu-inscricoes-campanhas' ); ?></li>
		<li><?php esc_html_e( 'Com a constante definida, ela tem prioridade sobre qualquer chave salva em Configurações, e a chave não fica no banco de dados.', 'cedu-inscricoes-campanhas' ); ?></li>
		<li><?php esc_html_e( 'Nunca coloque a chave no repositório do plugin ou do tema: o wp-config.php fica só no servidor.', 'cedu-inscricoes-campanhas' ); ?></li>
	</ul>
</div>
