<?php
/**
 * Tela "Informações e tutorial".
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;

$cedu_inscricoes_aberto    = Config::recebimento_aberto();
$cedu_inscricoes_chave     = Autenticacao::origem();
$cedu_inscricoes_edicao    = Config::edicao();
$cedu_inscricoes_termo     = '' !== $cedu_inscricoes_edicao ? get_term_by( 'slug', $cedu_inscricoes_edicao, Esquema::TAX_EDICAO ) : false;
$cedu_inscricoes_autor     = get_userdata( Config::autor() );
$cedu_inscricoes_ignorados = Informacoes::ignorados();
$cedu_inscricoes_status    = taxonomy_exists( Esquema::TAX_STATUS ) ? get_terms(
	array(
		'taxonomy'   => Esquema::TAX_STATUS,
		'hide_empty' => false,
	)
) : array();
$cedu_inscricoes_status    = is_array( $cedu_inscricoes_status ) ? $cedu_inscricoes_status : array();
$cedu_inscricoes_url_tax   = static fn( string $tax ): string => admin_url( 'edit-tags.php?taxonomy=' . $tax . '&post_type=' . Esquema::POST_TYPE );
$cedu_inscricoes_dados     = get_plugin_data( ARQUIVO, false, false );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- só exibe um aviso.
$cedu_inscricoes_limpo = ! empty( $_GET['limpo'] );
?>
<div class="wrap icamp icamp-info">
	<h1><?php esc_html_e( 'Informações e tutorial', 'cedu-inscricoes-campanhas' ); ?></h1>

	<?php require DIR . '/templates/admin/parte-aviso-documentacao.php'; ?>

	<ul class="icamp-sumario">
		<li><a href="#situacao"><?php esc_html_e( 'Situação atual', 'cedu-inscricoes-campanhas' ); ?></a></li>
		<li><a href="#como-funciona"><?php esc_html_e( 'Como funciona', 'cedu-inscricoes-campanhas' ); ?></a></li>
		<li><a href="#envio"><?php esc_html_e( 'Envio pela API', 'cedu-inscricoes-campanhas' ); ?></a></li>
		<li><a href="#chave-wp-config"><strong><?php esc_html_e( 'Chave no wp-config.php', 'cedu-inscricoes-campanhas' ); ?></strong></a></li>
		<li><a href="#json"><?php esc_html_e( 'Formato do JSON e campos', 'cedu-inscricoes-campanhas' ); ?></a></li>
		<li><a href="#campos-ignorados"><?php esc_html_e( 'Campos não configurados', 'cedu-inscricoes-campanhas' ); ?></a></li>
		<li><a href="#mudar-campos"><?php esc_html_e( 'Quando o formulário mudar', 'cedu-inscricoes-campanhas' ); ?></a></li>
		<li><a href="#edicoes"><?php esc_html_e( 'Edições', 'cedu-inscricoes-campanhas' ); ?></a></li>
		<li><a href="#status"><?php esc_html_e( 'Status (premiadas, menções…)', 'cedu-inscricoes-campanhas' ); ?></a></li>
		<li><a href="#recebimento"><?php esc_html_e( 'Abrir e fechar o recebimento', 'cedu-inscricoes-campanhas' ); ?></a></li>
		<li><a href="#nova-edicao"><?php esc_html_e( 'Checklist de nova edição', 'cedu-inscricoes-campanhas' ); ?></a></li>
		<li><a href="#atualizacoes"><?php esc_html_e( 'Atualizações do plugin', 'cedu-inscricoes-campanhas' ); ?></a></li>
		<li><a href="#gerenciar"><?php esc_html_e( 'Gerenciar, apagar e mídias', 'cedu-inscricoes-campanhas' ); ?></a></li>
		<li><a href="#cli"><?php esc_html_e( 'Linha de comando (WP-CLI)', 'cedu-inscricoes-campanhas' ); ?></a></li>
		<li><a href="#sobre"><?php esc_html_e( 'Sobre', 'cedu-inscricoes-campanhas' ); ?></a></li>
	</ul>

	<h2 id="situacao"><?php esc_html_e( 'Situação atual', 'cedu-inscricoes-campanhas' ); ?></h2>
	<table class="widefat striped icamp-situacao" role="presentation">
		<tbody>
			<tr>
				<td><?php esc_html_e( 'Recebimento', 'cedu-inscricoes-campanhas' ); ?></td>
				<td class="<?php echo $cedu_inscricoes_aberto ? 'icamp-ok' : 'icamp-alerta'; ?>"><?php echo $cedu_inscricoes_aberto ? esc_html__( 'Aberto', 'cedu-inscricoes-campanhas' ) : esc_html__( 'Fechado', 'cedu-inscricoes-campanhas' ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Chave da API', 'cedu-inscricoes-campanhas' ); ?></td>
				<td class="<?php echo 'constante' === $cedu_inscricoes_chave ? 'icamp-ok' : 'icamp-alerta'; ?>">
					<?php
					if ( 'constante' === $cedu_inscricoes_chave ) {
						esc_html_e( 'Configurada no wp-config.php (CEDU_INSCRICOES_API_KEY)', 'cedu-inscricoes-campanhas' );
					} elseif ( 'opcao' === $cedu_inscricoes_chave ) {
						esc_html_e( 'Configurada só em Configurações: inclua a constante no wp-config.php (recomendado)', 'cedu-inscricoes-campanhas' );
					} else {
						esc_html_e( 'Não configurada: a API recusa todos os envios. Inclua a constante no wp-config.php', 'cedu-inscricoes-campanhas' );
					}
					?>
					· <a href="#chave-wp-config"><?php esc_html_e( 'como fazer', 'cedu-inscricoes-campanhas' ); ?></a>
				</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Edição das inscrições novas', 'cedu-inscricoes-campanhas' ); ?></td>
				<td>
					<?php
					if ( $cedu_inscricoes_termo ) {
						/* translators: 1: nome da edição, 2: quantidade de inscrições. */
						echo esc_html( sprintf( __( '%1$s (%2$d inscrições)', 'cedu-inscricoes-campanhas' ), $cedu_inscricoes_termo->name, (int) $cedu_inscricoes_termo->count ) );
					} elseif ( '' === $cedu_inscricoes_edicao ) {
						esc_html_e( 'Nenhuma (não atribuir)', 'cedu-inscricoes-campanhas' );
					} else {
						echo '<span class="icamp-alerta">' . esc_html__( 'O termo configurado não existe mais', 'cedu-inscricoes-campanhas' ) . '</span>';
					}
					?>
				</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Autor das inscrições', 'cedu-inscricoes-campanhas' ); ?></td>
				<td><?php echo $cedu_inscricoes_autor ? esc_html( $cedu_inscricoes_autor->display_name ) : '—'; ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Inscrições (todas as edições)', 'cedu-inscricoes-campanhas' ); ?></td>
				<td><?php echo esc_html( number_format_i18n( Repositorio::contar() ) ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Versão do plugin', 'cedu-inscricoes-campanhas' ); ?></td>
				<td>
					<?php
					$cedu_inscricoes_atu = Atualizador::situacao();
					echo esc_html( VERSAO );
					if ( ! $cedu_inscricoes_atu['ligado'] ) {
						echo ' · ' . esc_html__( 'atualizações pelo painel desligadas no wp-config.php', 'cedu-inscricoes-campanhas' );
					} elseif ( '' !== $cedu_inscricoes_atu['versao'] && version_compare( $cedu_inscricoes_atu['versao'], VERSAO, '>' ) ) {
						/* translators: %s: versão disponível. */
						echo ' · <span class="icamp-alerta">' . esc_html( sprintf( __( 'versão %s disponível', 'cedu-inscricoes-campanhas' ), $cedu_inscricoes_atu['versao'] ) ) . '</span>';
					}
					?>
					· <a href="#atualizacoes"><?php esc_html_e( 'como atualizar', 'cedu-inscricoes-campanhas' ); ?></a>
				</td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Campos recebidos e não configurados', 'cedu-inscricoes-campanhas' ); ?></td>
				<td class="<?php echo $cedu_inscricoes_ignorados ? 'icamp-alerta' : 'icamp-ok'; ?>">
					<?php
					echo $cedu_inscricoes_ignorados
						/* translators: %d: quantidade. */
						? esc_html( sprintf( _n( '%d campo: veja abaixo', '%d campos: veja abaixo', count( $cedu_inscricoes_ignorados ), 'cedu-inscricoes-campanhas' ), count( $cedu_inscricoes_ignorados ) ) )
						: esc_html__( 'Nenhum', 'cedu-inscricoes-campanhas' );
					?>
				</td>
			</tr>
		</tbody>
	</table>
	<p><a href="<?php echo esc_url( Admin::url( Admin::PAGINA_CONFIGURACOES ) ); ?>"><?php esc_html_e( 'Alterar em Configurações', 'cedu-inscricoes-campanhas' ); ?></a></p>

	<h2 id="como-funciona"><?php esc_html_e( 'Como funciona', 'cedu-inscricoes-campanhas' ); ?></h2>
	<ol>
		<li><?php esc_html_e( 'O formulário externo envia cada inscrição como um JSON para a API do site, com a chave no cabeçalho.', 'cedu-inscricoes-campanhas' ); ?></li>
		<li><?php esc_html_e( 'O plugin confere a chave e se o recebimento está aberto, cria a inscrição (post do tipo "inscricoes") ou atualiza a existente com o mesmo identificador.', 'cedu-inscricoes-campanhas' ); ?></li>
		<li><?php esc_html_e( 'Cada campo configurado vira uma meta do post; a capa e as imagens públicas são baixadas para a Biblioteca de Mídia.', 'cedu-inscricoes-campanhas' ); ?></li>
		<li><?php esc_html_e( 'A inscrição recebe a edição configurada. O Status (premiada, menção etc.) é atribuído depois, à mão, pela equipe.', 'cedu-inscricoes-campanhas' ); ?></li>
		<li><?php esc_html_e( 'O tema exibe as inscrições (arquivo, página de cada uma e Portal de Resultados) lendo essas metas.', 'cedu-inscricoes-campanhas' ); ?></li>
	</ol>

	<h2 id="envio"><?php esc_html_e( 'Envio pela API', 'cedu-inscricoes-campanhas' ); ?></h2>
	<pre><?php echo esc_html( 'POST ' . rest_url( 'cedu/v1/inscricao' ) . "\nContent-Type: application/json\n" . Autenticacao::CABECALHO . ": <CHAVE>\n\n{ … JSON da inscrição … }" ); ?></pre>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Resposta', 'cedu-inscricoes-campanhas' ); ?></th><th><?php esc_html_e( 'Quando', 'cedu-inscricoes-campanhas' ); ?></th></tr></thead>
		<tbody>
			<tr><td><code>201</code></td><td><?php esc_html_e( 'Inscrição criada. Corpo: {"message": "…", "post_id": 123}', 'cedu-inscricoes-campanhas' ); ?></td></tr>
			<tr><td><code>200</code></td><td><?php esc_html_e( 'Inscrição atualizada (mesmo identificador de um envio anterior).', 'cedu-inscricoes-campanhas' ); ?></td></tr>
			<tr><td><code>400</code></td><td><?php esc_html_e( 'Corpo vazio, JSON inválido ou lista em vez de objeto.', 'cedu-inscricoes-campanhas' ); ?></td></tr>
			<tr><td><code>401</code></td><td><?php esc_html_e( 'Chave ausente ou errada.', 'cedu-inscricoes-campanhas' ); ?></td></tr>
			<tr><td><code>403</code></td><td><?php esc_html_e( 'Recebimento fechado (mensagem definida em Configurações).', 'cedu-inscricoes-campanhas' ); ?></td></tr>
			<tr><td><code>409</code></td><td><?php esc_html_e( 'O mesmo identificador está sendo processado por outro envio; tentar de novo em instantes.', 'cedu-inscricoes-campanhas' ); ?></td></tr>
			<tr><td><code>503</code></td><td><?php esc_html_e( 'Nenhuma chave configurada no site.', 'cedu-inscricoes-campanhas' ); ?></td></tr>
		</tbody>
	</table>
	<p class="description"><?php esc_html_e( 'A chave nunca é exibida aqui.', 'cedu-inscricoes-campanhas' ); ?></p>

	<h3 id="chave-wp-config"><?php esc_html_e( 'Chave no wp-config.php: inclua e confira', 'cedu-inscricoes-campanhas' ); ?></h3>
	<?php require DIR . '/templates/admin/parte-wp-config.php'; ?>
	<p>
		<?php
		printf(
			/* translators: %s: link para Configurações. */
			esc_html__( 'Para criar uma chave nova, use "Gerar chave para o wp-config.php" em %s. Ao trocar a chave, atualize ao mesmo tempo o wp-config.php e o formulário: enquanto forem diferentes, os envios são recusados (401).', 'cedu-inscricoes-campanhas' ),
			'<a href="' . esc_url( Admin::url( Admin::PAGINA_CONFIGURACOES ) ) . '">' . esc_html__( 'Configurações', 'cedu-inscricoes-campanhas' ) . '</a>'
		);
		?>
	</p>

	<h2 id="json"><?php esc_html_e( 'Formato do JSON e campos', 'cedu-inscricoes-campanhas' ); ?></h2>
	<p><?php esc_html_e( 'O corpo é um objeto JSON. Nenhum campo é obrigatório: o que não vier simplesmente não é gravado. Sem o identificador, cada envio cria uma inscrição nova; sem o título, ela se chama "Inscrição sem título". Exemplo resumido, gerado a partir da configuração atual:', 'cedu-inscricoes-campanhas' ); ?></p>
	<pre><?php echo esc_html( Informacoes::exemplo_json() ); ?></pre>
	<p><?php esc_html_e( 'Campos configurados hoje (config/campos.php). A coluna "Meta" é o nome que o tema usa para ler o valor:', 'cedu-inscricoes-campanhas' ); ?></p>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Campo no JSON', 'cedu-inscricoes-campanhas' ); ?></th>
				<th><?php esc_html_e( 'Tipo', 'cedu-inscricoes-campanhas' ); ?></th>
				<th><?php esc_html_e( 'Meta', 'cedu-inscricoes-campanhas' ); ?></th>
				<th><?php esc_html_e( 'Observação', 'cedu-inscricoes-campanhas' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( Informacoes::tabela_campos() as $cedu_inscricoes_linha ) : ?>
				<tr>
					<td><code><?php echo esc_html( $cedu_inscricoes_linha[0] ); ?></code></td>
					<td><?php echo esc_html( $cedu_inscricoes_linha[1] ); ?></td>
					<td><code><?php echo esc_html( $cedu_inscricoes_linha[2] ); ?></code></td>
					<td><?php echo esc_html( $cedu_inscricoes_linha[3] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description"><?php esc_html_e( 'Textos são gravados sem HTML. Listas e objetos são gravados como JSON. Números são gravados como inteiros.', 'cedu-inscricoes-campanhas' ); ?></p>

	<h2 id="campos-ignorados"><?php esc_html_e( 'Campos recebidos e não configurados', 'cedu-inscricoes-campanhas' ); ?></h2>
	<?php if ( $cedu_inscricoes_limpo ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Lista limpa.', 'cedu-inscricoes-campanhas' ); ?></p></div>
	<?php endif; ?>
	<p><?php esc_html_e( 'Campos que chegaram nos envios mas não estão em config/campos.php e por isso NÃO foram gravados. Só os nomes ficam registrados, nunca os valores. Se algum for um campo novo do formulário, configure-o (veja a seção seguinte).', 'cedu-inscricoes-campanhas' ); ?></p>
	<?php if ( $cedu_inscricoes_ignorados ) : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Campo', 'cedu-inscricoes-campanhas' ); ?></th>
					<th><?php esc_html_e( 'Tipo recebido', 'cedu-inscricoes-campanhas' ); ?></th>
					<th><?php esc_html_e( 'Envios', 'cedu-inscricoes-campanhas' ); ?></th>
					<th><?php esc_html_e( 'Último', 'cedu-inscricoes-campanhas' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $cedu_inscricoes_ignorados as $cedu_inscricoes_nome => $cedu_inscricoes_info ) : ?>
					<tr>
						<td><code><?php echo esc_html( (string) $cedu_inscricoes_nome ); ?></code></td>
						<td><?php echo esc_html( (string) ( $cedu_inscricoes_info['tipo'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) (int) ( $cedu_inscricoes_info['vezes'] ?? 0 ) ); ?></td>
						<td><?php echo esc_html( wp_date( 'd/m/Y H:i', (int) ( $cedu_inscricoes_info['ultima'] ?? 0 ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<form method="post">
			<?php wp_nonce_field( Informacoes::nonce_limpar() ); ?>
			<p><button type="submit" class="button" name="cedu_inscricoes_limpar_ignorados" value="1"><?php esc_html_e( 'Limpar a lista', 'cedu-inscricoes-campanhas' ); ?></button></p>
		</form>
	<?php else : ?>
		<p class="icamp-ok"><?php esc_html_e( 'Nenhum campo ignorado até agora.', 'cedu-inscricoes-campanhas' ); ?></p>
	<?php endif; ?>

	<h2 id="mudar-campos"><?php esc_html_e( 'Quando o formulário mudar', 'cedu-inscricoes-campanhas' ); ?></h2>
	<p><?php esc_html_e( 'Toda mudança de campos é feita num único arquivo do plugin, config/campos.php, no repositório (não direto no servidor). O passo a passo completo, com exemplos, está na documentação dos campos.', 'cedu-inscricoes-campanhas' ); ?></p>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Mudança no formulário', 'cedu-inscricoes-campanhas' ); ?></th><th><?php esc_html_e( 'O que fazer', 'cedu-inscricoes-campanhas' ); ?></th></tr></thead>
		<tbody>
			<tr><td><?php esc_html_e( 'Campo novo', 'cedu-inscricoes-campanhas' ); ?></td><td><?php esc_html_e( 'Acrescentar o nome na lista do tipo certo (texto, numero, lista, objeto ou evidencia). Para exibir no site, ajustar o single-inscricoes.php do tema.', 'cedu-inscricoes-campanhas' ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Campo removido', 'cedu-inscricoes-campanhas' ); ?></td><td><?php esc_html_e( 'Nada é obrigatório: ele só deixa de chegar. As inscrições antigas continuam com o valor.', 'cedu-inscricoes-campanhas' ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Campo renomeado', 'cedu-inscricoes-campanhas' ); ?></td><td><?php esc_html_e( 'Não renomear na lista. Acrescentar em "apelidos": \'nomeNovo\' => \'nomeAntigo\'. A meta continua com o nome antigo e o tema não precisa mudar.', 'cedu-inscricoes-campanhas' ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Identificador, título, capa, data ou proteção de imagens com outro nome', 'cedu-inscricoes-campanhas' ); ?></td><td><?php esc_html_e( 'Ajustar o bloco "papeis" do mesmo arquivo (ou usar um apelido).', 'cedu-inscricoes-campanhas' ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Mais ou menos imagens públicas', 'cedu-inscricoes-campanhas' ); ?></td><td><?php esc_html_e( 'Mudar "quantidade" em "imagens_publicas" (e o tema, se exibir mais imagens).', 'cedu-inscricoes-campanhas' ); ?></td></tr>
		</tbody>
	</table>
	<p><a class="button" href="<?php echo esc_url( Informacoes::url_doc() ); ?>"><?php esc_html_e( 'Abrir o guia dos campos', 'cedu-inscricoes-campanhas' ); ?></a></p>

	<h2 id="edicoes"><?php esc_html_e( 'Edições', 'cedu-inscricoes-campanhas' ); ?></h2>
	<p><?php esc_html_e( 'Cada inscrição pertence a uma edição da campanha (taxonomia "Edição"). O Portal de Resultados e os filtros do painel usam essa marcação para separar os anos.', 'cedu-inscricoes-campanhas' ); ?></p>
	<ol>
		<li>
			<?php
			printf(
				/* translators: %s: link para a tela de edições. */
				esc_html__( 'Crie o termo da nova edição em %s (por exemplo, nome e slug "2027").', 'cedu-inscricoes-campanhas' ),
				'<a href="' . esc_url( $cedu_inscricoes_url_tax( Esquema::TAX_EDICAO ) ) . '">' . esc_html__( 'Inscrições → Edição', 'cedu-inscricoes-campanhas' ) . '</a>'
			);
			?>
		</li>
		<li>
			<?php
			printf(
				/* translators: %s: link para Configurações. */
				esc_html__( 'Em %s, escolha a nova edição em "Edição das inscrições novas" e salve.', 'cedu-inscricoes-campanhas' ),
				'<a href="' . esc_url( Admin::url( Admin::PAGINA_CONFIGURACOES ) ) . '">' . esc_html__( 'Configurações', 'cedu-inscricoes-campanhas' ) . '</a>'
			);
			?>
		</li>
		<li><?php esc_html_e( 'A partir daí, toda inscrição recebida sem edição ganha o novo termo. As inscrições já existentes não mudam, e um reenvio não troca uma edição já marcada.', 'cedu-inscricoes-campanhas' ); ?></li>
		<li><?php esc_html_e( 'O plugin não cria termos: se o termo escolhido for apagado, nenhuma edição é atribuída (a situação acima avisa).', 'cedu-inscricoes-campanhas' ); ?></li>
	</ol>

	<h2 id="status"><?php esc_html_e( 'Status (premiadas, menções…)', 'cedu-inscricoes-campanhas' ); ?></h2>
	<p><?php esc_html_e( 'O Status marca o resultado da inscrição na avaliação. Ele nunca vem do formulário: é atribuído pela equipe depois que as inscrições são avaliadas.', 'cedu-inscricoes-campanhas' ); ?></p>
	<?php if ( $cedu_inscricoes_status ) : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Status', 'cedu-inscricoes-campanhas' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'cedu-inscricoes-campanhas' ); ?></th>
					<th><?php esc_html_e( 'Dentro de', 'cedu-inscricoes-campanhas' ); ?></th>
					<th><?php esc_html_e( 'Inscrições', 'cedu-inscricoes-campanhas' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $cedu_inscricoes_status as $cedu_inscricoes_t ) : ?>
					<?php $cedu_inscricoes_pai = $cedu_inscricoes_t->parent ? get_term( $cedu_inscricoes_t->parent, Esquema::TAX_STATUS ) : null; ?>
					<tr>
						<td><?php echo esc_html( $cedu_inscricoes_t->name ); ?></td>
						<td><code><?php echo esc_html( $cedu_inscricoes_t->slug ); ?></code></td>
						<td><?php echo $cedu_inscricoes_pai instanceof \WP_Term ? esc_html( $cedu_inscricoes_pai->name ) : '—'; ?></td>
						<td><?php echo esc_html( (string) (int) $cedu_inscricoes_t->count ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<ol>
		<li>
			<?php
			printf(
				/* translators: %s: link para a tela de status. */
				esc_html__( 'Os termos ficam em %s. Os slugs são usados pelo tema (página de inscrições e Portal de Resultados): não renomeie os slugs existentes sem ajustar o tema. Termos novos só aparecem no site se o tema for ajustado para eles.', 'cedu-inscricoes-campanhas' ),
				'<a href="' . esc_url( $cedu_inscricoes_url_tax( Esquema::TAX_STATUS ) ) . '">' . esc_html__( 'Inscrições → Status', 'cedu-inscricoes-campanhas' ) . '</a>'
			);
			?>
		</li>
		<li><?php esc_html_e( 'Para uma inscrição: abra-a em Inscrições → Todas as Inscrições → Editar e marque o Status na caixa lateral.', 'cedu-inscricoes-campanhas' ); ?></li>
		<li><?php esc_html_e( 'Para várias de uma vez: em Todas as Inscrições, marque as linhas, escolha "Editar" nas ações em massa e marque o Status. A "Edição rápida" de cada linha também permite.', 'cedu-inscricoes-campanhas' ); ?></li>
		<li><?php esc_html_e( 'Um Status "dentro de" outro (por exemplo, Destaque Nacional dentro de Premiada) conta também como o de cima na página de inscrições do site.', 'cedu-inscricoes-campanhas' ); ?></li>
		<li><?php esc_html_e( 'O Status não é alterado pelos reenvios do formulário.', 'cedu-inscricoes-campanhas' ); ?></li>
	</ol>

	<h2 id="recebimento"><?php esc_html_e( 'Abrir e fechar o recebimento', 'cedu-inscricoes-campanhas' ); ?></h2>
	<p>
		<?php
		printf(
			/* translators: %s: link para Configurações. */
			esc_html__( 'Em %s, o botão "Fechar recebimento" faz a API recusar todos os envios (HTTP 403, com a mensagem configurada) sem gravar nada. Use ao encerrar o prazo de inscrições ou durante uma manutenção. "Abrir recebimento" volta a aceitar. A chave e as inscrições não são afetadas.', 'cedu-inscricoes-campanhas' ),
			'<a href="' . esc_url( Admin::url( Admin::PAGINA_CONFIGURACOES ) ) . '">' . esc_html__( 'Configurações', 'cedu-inscricoes-campanhas' ) . '</a>'
		);
		?>
	</p>

	<h2 id="nova-edicao"><?php esc_html_e( 'Checklist de nova edição', 'cedu-inscricoes-campanhas' ); ?></h2>
	<ol>
		<li><?php esc_html_e( 'Compare o JSON do novo formulário com a tabela de campos acima. Se houver campos novos, removidos ou renomeados, ajuste config/campos.php no repositório e publique a nova versão do plugin.', 'cedu-inscricoes-campanhas' ); ?></li>
		<li><?php esc_html_e( 'Ajuste o tema (single-inscricoes.php) para exibir os campos novos, se for o caso.', 'cedu-inscricoes-campanhas' ); ?></li>
		<li><?php esc_html_e( 'Crie o termo da nova edição e escolha-o em Configurações.', 'cedu-inscricoes-campanhas' ); ?></li>
		<li><?php esc_html_e( 'Troque a imagem padrão em Configurações, se a campanha tiver uma nova arte.', 'cedu-inscricoes-campanhas' ); ?></li>
		<li><strong><?php esc_html_e( 'Confira se a constante CEDU_INSCRICOES_API_KEY está no wp-config.php (a situação no topo desta tela mostra). Se for trocar a chave, gere uma nova, atualize o wp-config.php e passe o mesmo valor a quem mantém o formulário.', 'cedu-inscricoes-campanhas' ); ?></strong></li>
		<li><?php esc_html_e( 'Abra o recebimento e faça um envio de teste. Confira em Gerenciar (edição, imagens, campos) e em "Campos não configurados" acima. Depois apague a inscrição de teste.', 'cedu-inscricoes-campanhas' ); ?></li>
		<li><?php esc_html_e( 'No fim do prazo, feche o recebimento. Após a avaliação, atribua os Status.', 'cedu-inscricoes-campanhas' ); ?></li>
	</ol>

	<h2 id="atualizacoes"><?php esc_html_e( 'Atualizações do plugin', 'cedu-inscricoes-campanhas' ); ?></h2>
	<p><?php esc_html_e( 'Há duas formas de instalar uma versão nova. Nas duas, as configurações, a chave e as inscrições ficam no banco e não são afetadas.', 'cedu-inscricoes-campanhas' ); ?></p>
	<h3><?php esc_html_e( 'Pelo painel (automática)', 'cedu-inscricoes-campanhas' ); ?></h3>
	<p>
		<?php
		printf(
			/* translators: %s: link para a tela de plugins. */
			esc_html__( 'O plugin consulta as versões publicadas no repositório no máximo a cada 12 horas. Quando há uma mais nova, ela aparece em %s com o link "Atualizar agora", como em qualquer plugin.', 'cedu-inscricoes-campanhas' ),
			'<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Plugins', 'cedu-inscricoes-campanhas' ) . '</a>'
		);
		?>
	</p>
	<h3><?php esc_html_e( 'À mão (baixando o zip)', 'cedu-inscricoes-campanhas' ); ?></h3>
	<ol>
		<li>
			<?php
			printf(
				/* translators: %s: link para as releases. */
				esc_html__( 'Baixe o arquivo cedu-inscricoes-campanhas-X.Y.Z.zip da versão desejada em %s (o arquivo anexado à versão, não o "Source code").', 'cedu-inscricoes-campanhas' ),
				'<a href="' . esc_url( Atualizador::url_releases() ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Releases do repositório', 'cedu-inscricoes-campanhas' ) . '</a>'
			);
			?>
		</li>
		<li>
			<?php
			printf(
				/* translators: %s: link para enviar plugin. */
				esc_html__( 'Em %s, escolha o zip e confirme "Substituir o atual pelo enviado".', 'cedu-inscricoes-campanhas' ),
				'<a href="' . esc_url( admin_url( 'plugin-install.php?tab=upload' ) ) . '">' . esc_html__( 'Plugins → Adicionar novo → Enviar plugin', 'cedu-inscricoes-campanhas' ) . '</a>'
			);
			?>
		</li>
		<li><?php esc_html_e( 'Também dá para substituir a pasta wp-content/plugins/cedu-inscricoes-campanhas/ por FTP.', 'cedu-inscricoes-campanhas' ); ?></li>
	</ol>
	<p class="description"><?php esc_html_e( 'A atualização substitui todos os arquivos do plugin, inclusive config/campos.php. Mudanças nos campos devem ser feitas no repositório e publicadas como nova versão. Para desligar as verificações automáticas: define( \'CEDU_INSCRICOES_ATUALIZAR\', false ); no wp-config.php.', 'cedu-inscricoes-campanhas' ); ?></p>

	<h2 id="gerenciar"><?php esc_html_e( 'Gerenciar, apagar e mídias', 'cedu-inscricoes-campanhas' ); ?></h2>
	<ul>
		<li><?php esc_html_e( 'Gerenciar: busca (título, nome, e-mail, instituição, município ou identificador), filtros por edição e status, e a visão "Possíveis duplicadas" (mesmo título e mesmo e-mail).', 'cedu-inscricoes-campanhas' ); ?></li>
		<li><?php esc_html_e( 'Apagar é definitivo (não passa pela lixeira) e leva junto a capa e as imagens públicas da inscrição. A imagem padrão compartilhada nunca é apagada. "Apagar todas desta lista" respeita os filtros aplicados e pede a confirmação APAGAR TUDO.', 'cedu-inscricoes-campanhas' ); ?></li>
		<li><?php esc_html_e( 'Apagar uma inscrição pela tela nativa do WordPress (esvaziar a lixeira) também apaga a mídia dela.', 'cedu-inscricoes-campanhas' ); ?></li>
		<li><?php esc_html_e( 'Mídias: só consulta. Mostra a origem de cada arquivo; não há exclusão de mídias por aqui.', 'cedu-inscricoes-campanhas' ); ?></li>
	</ul>

	<h2 id="cli"><?php esc_html_e( 'Linha de comando (WP-CLI)', 'cedu-inscricoes-campanhas' ); ?></h2>
	<pre>wp inscricoes importar inscricao.json
wp inscricoes listar --edicao=2026 --format=csv
wp inscricoes listar --duplicadas
wp inscricoes apagar 123 456
wp inscricoes apagar --todas --edicao=2026 --dry-run
wp inscricoes chave status</pre>

	<h2 id="sobre"><?php esc_html_e( 'Sobre', 'cedu-inscricoes-campanhas' ); ?></h2>
	<p>
		<?php
		printf(
			/* translators: 1: nome do plugin, 2: versão. */
			esc_html__( '%1$s, versão %2$s.', 'cedu-inscricoes-campanhas' ),
			esc_html( (string) ( $cedu_inscricoes_dados['Name'] ?? '' ) ),
			esc_html( VERSAO )
		);
		?>
		<?php esc_html_e( 'Desenvolvido por', 'cedu-inscricoes-campanhas' ); ?>
		<a href="<?php echo esc_url( (string) ( $cedu_inscricoes_dados['AuthorURI'] ?? '' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) ( $cedu_inscricoes_dados['Author'] ?? '' ) ); ?></a>.
	</p>
	<p>
		<a href="<?php echo esc_url( Config::URL_REPOSITORIO ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Repositório', 'cedu-inscricoes-campanhas' ); ?></a>
		·
		<a href="<?php echo esc_url( Informacoes::url_doc() ); ?>"><?php esc_html_e( 'Guia dos campos', 'cedu-inscricoes-campanhas' ); ?></a>
	</p>
</div>
