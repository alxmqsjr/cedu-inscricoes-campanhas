<?php
/**
 * Teste de ponta a ponta. Só para ambiente de desenvolvimento.
 *
 *     wp eval-file wp-content/plugins/cedu-inscricoes-campanhas/tests/smoke.php
 *
 * Cria inscrições de teste (baixando imagens da URL padrão), confere o
 * resultado e apaga tudo o que criou no fim, inclusive a chave temporária.
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( Importador::class ) ) {
	echo "Plugin inativo.\n";
	return;
}

$GLOBALS['cedu_inscricoes_t'] = array( 0, 0 );

/**
 * Registra uma verificação.
 *
 * @param string $nome  Descrição.
 * @param bool   $cond  Resultado.
 * @param string $extra Detalhe.
 */
function cedu_inscricoes_t( string $nome, bool $cond, string $extra = '' ): void {
	++$GLOBALS['cedu_inscricoes_t'][ $cond ? 0 : 1 ];
	echo ( $cond ? '[OK]    ' : '[FALHA] ' ) . $nome . ( '' !== $extra ? "  ({$extra})" : '' ) . "\n";
}

/**
 * Envia ao endpoint público.
 *
 * @param mixed       $corpo Corpo (array é codificado em JSON).
 * @param string|null $chave Cabeçalho da chave.
 */
function cedu_inscricoes_req( $corpo, ?string $chave ) {
	$r = new WP_REST_Request( 'POST', '/cedu/v1/inscricao' );
	$r->set_header( 'Content-Type', 'application/json' );
	if ( null !== $chave ) {
		$r->set_header( Autenticacao::CABECALHO, $chave );
	}
	$r->set_body( is_string( $corpo ) ? $corpo : (string) wp_json_encode( $corpo ) );
	return rest_do_request( $r );
}

/**
 * Chama uma rota administrativa.
 *
 * @param string               $rota  Rota.
 * @param array<string, mixed> $corpo Corpo.
 */
function cedu_inscricoes_admin( string $rota, array $corpo ) {
	$r = new WP_REST_Request( 'POST', '/' . Rest_Admin::NAMESPACE . '/' . $rota );
	$r->set_header( 'Content-Type', 'application/json' );
	$r->set_body( (string) wp_json_encode( $corpo ) );
	return rest_do_request( $r );
}

/** Existe? */
function cedu_inscricoes_existe( int $id ): bool {
	return null !== get_post( $id );
}

$prefixo   = 'ICAMP-TESTE-' . time();
$img       = Config::IMAGEM_URL_PADRAO;
$admin_id  = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
$hash_orig = get_option( Config::OPCAO_CHAVE_HASH, null );
$padrao_antes = (int) get_option( Config::OPCAO_IMAGEM_ID, 0 );
$chave     = wp_generate_password( 32, false );
$receb_orig    = get_option( Config::OPCAO_RECEBIMENTO, null );
$ignorad_orig  = get_option( Config::OPCAO_CAMPOS_IGNORADOS, null );

wp_set_current_user( 0 );

echo "== Chave ==\n";
delete_option( Config::OPCAO_CHAVE_HASH );
if ( '' === Autenticacao::origem() ) {
	cedu_inscricoes_t( 'sem chave configurada → 503', 503 === cedu_inscricoes_req( array( 'id' => 'x' ), 'qualquer' )->get_status() );
}
cedu_inscricoes_t( 'chave curta é recusada', ! Autenticacao::salvar( 'curta' ) );
Autenticacao::salvar( $chave );
cedu_inscricoes_t( 'banco guarda só o hash', get_option( Config::OPCAO_CHAVE_HASH ) === hash( 'sha256', $chave ) );
if ( 'opcao' === Autenticacao::origem() ) {
	cedu_inscricoes_t( 'sem cabeçalho → 401', 401 === cedu_inscricoes_req( array( 'id' => 'x' ), null )->get_status() );
	cedu_inscricoes_t( 'chave errada → 401', 401 === cedu_inscricoes_req( array( 'id' => 'x' ), $chave . 'x' )->get_status() );
}
cedu_inscricoes_t( 'corpo vazio → 400', 400 === cedu_inscricoes_req( '', $chave )->get_status() );
cedu_inscricoes_t( 'JSON inválido → 400', 400 === cedu_inscricoes_req( '{quebrado', $chave )->get_status() );
cedu_inscricoes_t( 'lista JSON em vez de objeto → 400', 400 === cedu_inscricoes_req( '[{"id":"x"}]', $chave )->get_status() );

echo "\n== Recebimento ==\n";
update_option( Config::OPCAO_RECEBIMENTO, '0', false );
$antes = Repositorio::contar();
$r     = cedu_inscricoes_req( array( 'id' => $prefixo . '-F', 'tituloCampanha' => $prefixo . ' fechado' ), $chave );
cedu_inscricoes_t( 'fechado → 403 com a mensagem', 403 === $r->get_status() && Config::mensagem_fechado() === ( $r->get_data()['message'] ?? '' ) );
cedu_inscricoes_t( 'fechado → nada gravado', Repositorio::contar() === $antes );
cedu_inscricoes_t( 'fechado sem chave → 401 (não revela o estado)', 401 === cedu_inscricoes_req( array( 'id' => 'x' ), null )->get_status() );
update_option( Config::OPCAO_RECEBIMENTO, '1', false );
delete_option( Config::OPCAO_CAMPOS_IGNORADOS );

echo "\n== Criação ==\n";
$fid     = $prefixo . '-A';
$payload = array(
	'id'                     => $fid,
	'tituloCampanha'         => $prefixo . ' Campanha "aspas" \\ barra',
	'nomeCompleto'           => 'Pessoa Teste',
	'email'                  => 'teste@example.org',
	'estado'                 => 'SP',
	'municipio'              => 'São José dos Campos',
	'paraQue'                => "Linha 1\nC:\\pasta com barra",
	'eixosTematicos'         => array( 'A', 'B "citado"' ),
	'materiais'              => array( array( 'descricao' => 'Cartaz \\ barra', 'quantidade' => 3 ) ),
	'pontuacao'              => '87',
	'submittedAt'            => array( 'seconds' => 1760000000 ),
	'evidenciasAcoes'        => array(
		array( 'url' => 'https://example.org/v0/b/form/o/rascunhos%2Fabc%2Facoes%2Ffoto.jpeg?alt=media&token=t-1', 'caption' => 'Ação "1"' ),
		array( 'url' => '', 'caption' => 'sem url' ),
		'não é objeto',
	),
	'imagemCapaUrl'          => $img,
	'imagem_1_publica'       => array( 'url' => $img, 'caption' => '', 'source' => 'evidenciasAcoes', 'sourceIndex' => 0 ),
	'imagem_2_publica'       => array( 'url' => $img, 'caption' => 'Legenda própria' ),
	'imagem_3_publica'       => null,
	'nomeSocial'             => array( 'array', 'em', 'campo', 'de', 'texto' ),
	'linksMateriais'         => array( 'https://example.org/arquivo%20final.pdf?a=1&b=2' ),
	'grupoOrganizador'       => array( 'nome' => '<script>alert(1)</script>Grupo <b>X</b>', 'membros' => 4 ),
	'imagem_4_publica'       => array( 'url' => 'https://educacao.cemaden.gov.br/', 'caption' => 'página HTML, não imagem' ),
	'imagem_5_publica'       => array( 'url' => 'https://example.org/v0/b/form/o/rascunhos%2Fabc%2Falcancesocial%2Ffoto.jpeg?alt=media&token=t-5', 'caption' => 'formato Firebase' ),
	'campoNovoTeste'         => 'valor-secreto-nao-pode-ser-guardado',
	'_thumbnail_id'          => 999999,
);
$t0  = microtime( true );
$res = cedu_inscricoes_req( $payload, $chave );
$pid = (int) ( $res->get_data()['post_id'] ?? 0 );
cedu_inscricoes_t( 'criação → 201', 201 === $res->get_status(), round( microtime( true ) - $t0, 1 ) . 's' );
cedu_inscricoes_t( 'firebase_id gravado a partir de "id"', get_post_meta( $pid, 'firebase_id', true ) === $fid );
cedu_inscricoes_t( 'autor da inscrição = configurado', (int) get_post_field( 'post_author', $pid ) === Config::autor() );
cedu_inscricoes_t( 'texto com quebra e barra preservado', get_post_meta( $pid, 'paraQue', true ) === "Linha 1\nC:\\pasta com barra" );
cedu_inscricoes_t( 'lista com aspas é JSON válido', json_decode( (string) get_post_meta( $pid, 'eixosTematicos', true ), true ) === array( 'A', 'B "citado"' ) );
cedu_inscricoes_t( 'objeto com barra é JSON válido', ( json_decode( (string) get_post_meta( $pid, 'materiais', true ), true )[0]['descricao'] ?? '' ) === 'Cartaz \\ barra' );
cedu_inscricoes_t( 'pontuacao e submitted_at', get_post_meta( $pid, 'pontuacao', true ) === '87' && get_post_meta( $pid, 'submitted_at', true ) === '1760000000' );
$ev = json_decode( (string) get_post_meta( $pid, 'evidenciasAcoes', true ), true );
cedu_inscricoes_t( 'evidências: só itens com URL', is_array( $ev ) && 1 === count( $ev ) && 'Ação "1"' === $ev[0]['caption'] );
cedu_inscricoes_t( 'evidência: URL do Firebase com %2F e token preservada', 'https://example.org/v0/b/form/o/rascunhos%2Fabc%2Facoes%2Ffoto.jpeg?alt=media&token=t-1' === ( $ev[0]['url'] ?? '' ) );
cedu_inscricoes_t( 'campo de texto recebido como array é ignorado', '' === get_post_meta( $pid, 'nomeSocial', true ) && ! metadata_exists( 'post', $pid, 'nomeSocial' ) );
$i1 = json_decode( (string) get_post_meta( $pid, 'imagem_1_publica', true ), true );
$i2 = json_decode( (string) get_post_meta( $pid, 'imagem_2_publica', true ), true );
cedu_inscricoes_t( 'imagem_1_publica baixada', ! empty( $i1['local'] ) && $i1['attachment_id'] > 0 );
cedu_inscricoes_t( 'imagem_1: título pela evidência de origem', get_post_field( 'post_title', (int) ( $i1['attachment_id'] ?? 0 ), 'raw' ) === 'Ação "1"' );
cedu_inscricoes_t( 'imagem_2: título pela própria legenda', get_post_field( 'post_title', (int) ( $i2['attachment_id'] ?? 0 ), 'raw' ) === 'Legenda própria' );
cedu_inscricoes_t( 'título da inscrição com aspas e barra', get_post_field( 'post_title', $pid, 'raw' ) === $prefixo . ' Campanha "aspas" \\ barra' );
cedu_inscricoes_t( 'imagem_3 null não gera meta', ! metadata_exists( 'post', $pid, 'imagem_3_publica' ) );
cedu_inscricoes_t( 'mídia baixada tem autor', (int) get_post_field( 'post_author', (int) ( $i1['attachment_id'] ?? 0 ) ) === Config::autor() );
$capa1 = (int) get_post_thumbnail_id( $pid );
cedu_inscricoes_t( 'capa baixada de imagemCapaUrl', $capa1 > 0 && (int) get_post_field( 'post_parent', $capa1 ) === $pid );
$ed = wp_get_object_terms( $pid, Esquema::TAX_EDICAO, array( 'fields' => 'slugs' ) );
if ( get_term_by( 'slug', Config::edicao(), Esquema::TAX_EDICAO ) ) {
	cedu_inscricoes_t( 'edição atribuída', array( Config::edicao() ) === $ed );
}
$antigos = Midia::anexos_do_importador( $pid );
cedu_inscricoes_t( '3 anexos do importador (capa + 2 públicas)', 3 === count( $antigos ) );
cedu_inscricoes_t( 'lista: URL com %20 e & preservada', json_decode( (string) get_post_meta( $pid, 'linksMateriais', true ), true ) === array( 'https://example.org/arquivo%20final.pdf?a=1&b=2' ) );
$grupo = json_decode( (string) get_post_meta( $pid, 'grupoOrganizador', true ), true );
cedu_inscricoes_t( 'objeto: HTML removido, número mantido', is_array( $grupo ) && ! str_contains( (string) $grupo['nome'], '<' ) && 4 === $grupo['membros'], (string) ( $grupo['nome'] ?? '' ) );
$i4 = json_decode( (string) get_post_meta( $pid, 'imagem_4_publica', true ), true );
cedu_inscricoes_t( 'URL que não é imagem não vira mídia', is_array( $i4 ) && empty( $i4['local'] ) && 0 === $i4['attachment_id'] );
$i5 = json_decode( (string) get_post_meta( $pid, 'imagem_5_publica', true ), true );
cedu_inscricoes_t( 'imagem pública: URL do Firebase com %2F e token preservada', 'https://example.org/v0/b/form/o/rascunhos%2Fabc%2Falcancesocial%2Ffoto.jpeg?alt=media&token=t-5' === ( $i5['url_original'] ?? '' ) );
cedu_inscricoes_t( 'campo desconhecido não é gravado', ! metadata_exists( 'post', $pid, 'campoNovoTeste' ) );
cedu_inscricoes_t( 'campo "_thumbnail_id" do JSON não sobrescreve a capa', 999999 !== (int) get_post_thumbnail_id( $pid ) );
$ign = (array) get_option( Config::OPCAO_CAMPOS_IGNORADOS, array() );
cedu_inscricoes_t( 'nome do campo desconhecido registrado', isset( $ign['campoNovoTeste'] ) && 'string' === $ign['campoNovoTeste']['tipo'] );
cedu_inscricoes_t( 'valor do campo desconhecido NÃO registrado', ! str_contains( (string) wp_json_encode( $ign ), 'valor-secreto' ) );

echo "\n== Reenvio (mesmo id, agora opcao3) ==\n";
// Uma imagem anexada à mão à inscrição não pode sumir no reenvio.
$manual = Midia::baixar( $img, $pid, 'anexo manual' );
$payload['tituloCampanha']           = $prefixo . ' Campanha v2';
$payload['termoImagemProtecaoOpcao'] = 'opcao3';
unset( $payload['imagem_2_publica'] );
$res2 = cedu_inscricoes_req( $payload, $chave );
cedu_inscricoes_t( 'reenvio → 200 no mesmo post', 200 === $res2->get_status() && (int) $res2->get_data()['post_id'] === $pid );
cedu_inscricoes_t( 'título atualizado', get_the_title( $pid ) === $prefixo . ' Campanha v2' );
cedu_inscricoes_t( 'mídia antiga substituída foi apagada', array() === array_filter( $antigos, 'CemadenInscricoes\cedu_inscricoes_existe' ) );
cedu_inscricoes_t( 'anexo manual preservado', cedu_inscricoes_existe( $manual ) );
cedu_inscricoes_t( 'imagem_2 ausente no reenvio sumiu', ! metadata_exists( 'post', $pid, 'imagem_2_publica' ) );
$padrao = (int) get_post_thumbnail_id( $pid );
cedu_inscricoes_t( 'opcao3 usa a imagem padrão compartilhada', $padrao > 0 && Midia::eh_imagem_padrao( $padrao ) && 0 === (int) get_post_field( 'post_parent', $padrao ) );

$res3 = cedu_inscricoes_req( array( 'id' => $prefixo . '-B', 'tituloCampanha' => $prefixo . ' Outra opcao3', 'termoImagemProtecaoOpcao' => 'opcao3', 'email' => 'dup@example.org' ), $chave );
$pid_b = (int) ( $res3->get_data()['post_id'] ?? 0 );
cedu_inscricoes_t( 'segunda opcao3 reaproveita o mesmo anexo', (int) get_post_thumbnail_id( $pid_b ) === $padrao );

echo "\n== Apelidos e filtro de campos ==\n";
$filtro = static function ( array $c ): array {
	$c['apelidos']['cidade'] = 'municipio';
	$c['texto'][]            = 'publicoTeste';
	$c['apelidos']['_ruim']  = 'municipio';
	return $c;
};
add_filter( 'cedu_inscricoes_campos', $filtro );
Esquema::recarregar();
$ra  = cedu_inscricoes_req( array( 'id' => $prefixo . '-AP', 'tituloCampanha' => $prefixo . ' apelido', 'cidade' => 'Cidade Nova', 'publicoTeste' => 'Estudantes' ), $chave );
$pap = (int) ( $ra->get_data()['post_id'] ?? 0 );
cedu_inscricoes_t( 'apelido grava na meta configurada', 'Cidade Nova' === get_post_meta( $pap, 'municipio', true ) && ! metadata_exists( 'post', $pap, 'cidade' ) );
cedu_inscricoes_t( 'campo novo pelo filtro é gravado', 'Estudantes' === get_post_meta( $pap, 'publicoTeste', true ) );
cedu_inscricoes_t( 'apelido com nome inválido é descartado', ! isset( Esquema::apelidos()['_ruim'] ) );
remove_filter( 'cedu_inscricoes_campos', $filtro );
Esquema::recarregar();
Repositorio::apagar( $pap );

echo "\n== Busca e duplicadas ==\n";
$d1 = (int) ( cedu_inscricoes_req( array( 'tituloCampanha' => $prefixo . ' Dup', 'email' => 'Dup@Example.org ' ), $chave )->get_data()['post_id'] ?? 0 );
$d2 = (int) ( cedu_inscricoes_req( array( 'tituloCampanha' => $prefixo . ' dup ', 'email' => 'dup@example.org' ), $chave )->get_data()['post_id'] ?? 0 );
cedu_inscricoes_t( 'sem "id": cada envio cria uma inscrição', $d1 > 0 && $d2 > 0 && $d1 !== $d2 );
$dups = Repositorio::ids( array( 'duplicadas' => '1', 'busca' => $prefixo ), 50 );
cedu_inscricoes_t( 'possíveis duplicadas (título e e-mail sem caixa/espaços)', in_array( $d1, $dups, true ) && in_array( $d2, $dups, true ) && ! in_array( $pid_b, $dups, true ) );
cedu_inscricoes_t( 'busca pelo ID do formulário', Repositorio::ids( array( 'busca' => $fid ), 5 ) === array( $pid ) );
cedu_inscricoes_t( 'contagem pela busca', 4 === Repositorio::contar( array( 'busca' => $prefixo ) ) );

echo "\n== Rotas administrativas ==\n";
cedu_inscricoes_t( 'sem login → recusado', in_array( cedu_inscricoes_admin( 'excluir', array( 'ids' => array( $d1 ) ) )->get_status(), array( 401, 403 ), true ) );
wp_set_current_user( $admin_id );
cedu_inscricoes_t( 'mais de 20 IDs → 400', 400 === cedu_inscricoes_admin( 'excluir', array( 'ids' => range( 1, 21 ) ) )->get_status() );
$r = cedu_inscricoes_admin( 'excluir', array( 'ids' => array( $d1, $padrao ) ) );
cedu_inscricoes_t( 'excluir: apaga inscrição, ignora o que não é inscrição', 1 === $r->get_data()['apagadas'] && ! cedu_inscricoes_existe( $d1 ) && cedu_inscricoes_existe( $padrao ) );

echo "\n== Telas ==\n";
require_once ABSPATH . 'wp-admin/includes/admin.php';
$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST'] ?? (string) wp_parse_url( home_url(), PHP_URL_HOST );
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/wp-admin/edit.php';
foreach ( array( 'class-tabela-inscricoes', 'class-tabela-midias', 'class-configuracoes', 'class-markdown', 'class-informacoes', 'class-colunas', 'class-admin' ) as $arq ) {
	require_once DIR . "/includes/admin/{$arq}.php";
}
set_current_screen( 'inscricoes_page_' . Admin::PAGINA_GERENCIAR );
$_GET = array( 's' => $prefixo );
$t0   = microtime( true );
$q0   = get_num_queries();
ob_start();
Admin::pagina_gerenciar();
$html = (string) ob_get_clean();
cedu_inscricoes_t( 'Gerenciar renderiza com a busca', str_contains( $html, $prefixo . ' Campanha v2' ) && str_contains( $html, 'icamp-apagar-uma' ), round( microtime( true ) - $t0, 2 ) . 's, ' . ( get_num_queries() - $q0 ) . ' consultas' );
$_GET = array();
$q0   = get_num_queries();
ob_start();
Admin::pagina_gerenciar();
$html = (string) ob_get_clean();
cedu_inscricoes_t( 'Gerenciar sem filtros (50 por página)', str_contains( $html, 'Possíveis duplicadas' ), ( get_num_queries() - $q0 ) . ' consultas, ' . round( strlen( $html ) / 1024 ) . ' KB' );

set_current_screen( 'inscricoes_page_' . Admin::PAGINA_MIDIAS );
foreach ( Repositorio::ORIGENS_MIDIA as $origem ) {
	$_GET = array( 'origem' => $origem );
	$q0   = get_num_queries();
	ob_start();
	Admin::pagina_midias();
	$html = (string) ob_get_clean();
	cedu_inscricoes_t( "Mídias ({$origem}) renderiza sem botões de exclusão", str_contains( $html, 'icamp-arquivo' ) || str_contains( $html, 'Nenhuma mídia' ), ( get_num_queries() - $q0 ) . ' consultas' );
	cedu_inscricoes_t( "Mídias ({$origem}) não tem checkbox nem ação em massa", ! str_contains( $html, 'type="checkbox"' ) && ! str_contains( $html, 'bulk-action' ) );
}
$_GET = array();
ob_start();
Configuracoes::registrar_campos();
Configuracoes::pagina();
$html = (string) ob_get_clean();
cedu_inscricoes_t( 'Configurações renderiza sem expor a chave', str_contains( $html, 'Chave da API' ) && ! str_contains( $html, $chave ) );
cedu_inscricoes_t( 'Configurações mostra o recebimento e o link da documentação', str_contains( $html, 'Fechar recebimento' ) && str_contains( $html, 'doc=campos' ) );
cedu_inscricoes_t( 'Configurações destaca a constante do wp-config.php', str_contains( $html, esc_html( "define( 'CEDU_INSCRICOES_API_KEY', 'COLE-A-CHAVE-AQUI' );" ) ) && str_contains( $html, 'Gerar chave para o wp-config.php' ) );
cedu_inscricoes_t( 'autor das inscrições é administrador', Config::eh_administrador( Config::autor() ) );
$nao_admin = (int) ( get_users( array( 'role__not_in' => array( 'administrator' ), 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
if ( $nao_admin ) {
	cedu_inscricoes_t( 'Configurações recusa autor que não é administrador', Configuracoes::sanitizar_autor( $nao_admin ) !== $nao_admin );
}
cedu_inscricoes_t( 'Configurações aceita administrador como autor', Configuracoes::sanitizar_autor( $admin_id ) === $admin_id );
ob_start();
Informacoes::pagina();
$html = (string) ob_get_clean();
cedu_inscricoes_t( 'Informações renderiza tabela de campos, campos ignorados e link', str_contains( $html, 'tituloCampanha' ) && str_contains( $html, 'campoNovoTeste' ) && str_contains( $html, 'doc=campos' ) && ! str_contains( $html, 'valor-secreto' ) );
cedu_inscricoes_t( 'Informações: exemplo JSON válido', is_array( json_decode( Informacoes::exemplo_json(), true ) ) );
cedu_inscricoes_t( 'Informações destaca a constante do wp-config.php', str_contains( $html, 'id="chave-wp-config"' ) && str_contains( $html, 'COLE-A-CHAVE-AQUI' ) );
$_GET = array( 'doc' => 'campos' );
ob_start();
Informacoes::pagina();
$html = (string) ob_get_clean();
$_GET = array();
cedu_inscricoes_t( 'guia dos campos no painel (títulos, tabelas e código)', str_contains( $html, '<h2>' ) && str_contains( $html, '<table' ) && str_contains( $html, '<pre><code>' ) && str_contains( $html, '<code>apelidos</code>' ) );
cedu_inscricoes_t( 'guia dos campos sem marcação crua nem caracteres de controle', ! str_contains( $html, '**' ) && ! str_contains( $html, '```' ) && ! str_contains( $html, "\x00" ) );
cedu_inscricoes_t( 'Markdown escapa HTML do texto', ! str_contains( Markdown::html( "Texto <script>alert(1)</script> e `<b>código</b>`\n\n[x](javascript:alert(1))" ), '<script>' ) && ! str_contains( Markdown::html( '[x](javascript:alert(1))' ), 'href' ) );

echo "\n== Exclusão ==\n";
$anexos_pid = Midia::anexos_da_inscricao( $pid );
$r = cedu_inscricoes_admin( 'excluir-filtro', array( 'busca' => $prefixo, 'lote' => 2 ) );
$d = $r->get_data();
cedu_inscricoes_t( 'excluir-filtro apaga em lotes', 200 === $r->get_status() && 2 === $d['apagadas'] && 1 === $d['restantes'] && ! $d['fim'] );
$r = cedu_inscricoes_admin( 'excluir-filtro', array( 'busca' => $prefixo, 'lote' => 5 ) );
cedu_inscricoes_t( 'excluir-filtro termina', ! empty( $r->get_data()['fim'] ) && 0 === Repositorio::contar( array( 'busca' => $prefixo ) ) );
cedu_inscricoes_t( 'mídia das inscrições apagada (inclui anexo manual)', array() === array_filter( $anexos_pid, 'CemadenInscricoes\cedu_inscricoes_existe' ) );
cedu_inscricoes_t( 'imagem padrão compartilhada preservada', cedu_inscricoes_existe( $padrao ) );

// Exclusão pela tela nativa (wp_delete_post) também leva a mídia.
wp_set_current_user( 0 );
$n   = (int) ( cedu_inscricoes_req( array( 'id' => $prefixo . '-C', 'tituloCampanha' => $prefixo . ' Nativa', 'imagemCapaUrl' => $img ), $chave )->get_data()['post_id'] ?? 0 );
$cap = (int) get_post_thumbnail_id( $n );
wp_delete_post( $n, true );
cedu_inscricoes_t( 'wp_delete_post nativo apaga a mídia', $cap > 0 && ! cedu_inscricoes_existe( $cap ) );

echo "\n== Atualizador (só detecção, resposta do GitHub simulada) ==\n";
$cedu_inscricoes_resposta = null;
$simular = static function ( $pre, $args, $url ) use ( &$cedu_inscricoes_resposta ) {
	if ( ! str_contains( (string) $url, 'api.github.com/repos/' . Atualizador::REPOSITORIO ) ) {
		return $pre;
	}
	return $cedu_inscricoes_resposta;
};
add_filter( 'pre_http_request', $simular, 10, 3 );
$release = static function ( array $extra = array(), int $codigo = 200 ) {
	$base = array(
		'tag_name'     => 'v9.9.9',
		'draft'        => false,
		'prerelease'   => false,
		'html_url'     => 'https://github.com/' . Atualizador::REPOSITORIO . '/releases/tag/v9.9.9',
		'body'         => 'Notas <script>x</script>',
		'published_at' => '2026-11-18T00:00:00Z',
		'assets'       => array(
			array(
				'name'                 => 'cedu-inscricoes-campanhas-9.9.9.zip',
				'browser_download_url' => 'https://github.com/' . Atualizador::REPOSITORIO . '/releases/download/v9.9.9/cedu-inscricoes-campanhas-9.9.9.zip',
			),
		),
	);
	return array(
		'headers'  => array(),
		'body'     => (string) wp_json_encode( array_merge( $base, $extra ) ),
		'response' => array(
			'code'    => $codigo,
			'message' => 'x',
		),
		'cookies'  => array(),
	);
};
$transient_orig = get_transient( Atualizador::TRANSIENT );
delete_transient( Atualizador::TRANSIENT );

cedu_inscricoes_t( 'filtro de atualização registrado', false !== has_filter( 'update_plugins_github.com', array( Atualizador::class, 'verificar' ) ) );
$cedu_inscricoes_resposta = $release();
$oferta = apply_filters( 'update_plugins_github.com', false, array(), plugin_basename( ARQUIVO ) );
cedu_inscricoes_t( 'release válida → oferece a versão 9.9.9', is_array( $oferta ) && '9.9.9' === $oferta['version'] && str_ends_with( $oferta['package'], '.zip' ) );
$outro = apply_filters( 'update_plugins_github.com', false, array(), 'outro-plugin/outro.php' );
cedu_inscricoes_t( 'não responde por outro plugin', false === $outro || ( is_array( $outro ) && ( $outro['slug'] ?? '' ) !== Atualizador::SLUG ) );
$info = Atualizador::detalhes( false, 'plugin_information', (object) array( 'slug' => Atualizador::SLUG ) );
cedu_inscricoes_t( '"Ver detalhes" com notas escapadas', is_object( $info ) && ! str_contains( (string) $info->sections['changelog'], '<script>' ) );

$casos = array(
	'zip de outro repositório'   => array( 'assets' => array( array( 'name' => 'cedu-inscricoes-campanhas.zip', 'browser_download_url' => 'https://github.com/outra/conta/releases/download/v9.9.9/cedu-inscricoes-campanhas.zip' ) ) ),
	'só o "Source code"'         => array( 'assets' => array() ),
	'zip com outro nome'         => array( 'assets' => array( array( 'name' => 'malicioso.zip', 'browser_download_url' => 'https://github.com/' . Atualizador::REPOSITORIO . '/releases/download/v9.9.9/malicioso.zip' ) ) ),
	'pre-release'                => array( 'prerelease' => true ),
	'rascunho'                   => array( 'draft' => true ),
	'tag que não é versão'       => array( 'tag_name' => 'ultima' ),
);
foreach ( $casos as $nome => $extra ) {
	$cedu_inscricoes_resposta = $release( $extra );
	cedu_inscricoes_t( "recusa: {$nome}", null === Atualizador::buscar() );
}
$cedu_inscricoes_resposta = $release( array(), 404 );
cedu_inscricoes_t( 'repositório sem release (404) → nada oferecido, sem erro', null === Atualizador::buscar() );
$cedu_inscricoes_resposta = new \WP_Error( 'http_request_failed', 'sem rede' );
cedu_inscricoes_t( 'falha de rede → nada oferecido, sem erro', null === Atualizador::buscar() );

remove_filter( 'pre_http_request', $simular, 10 );
false === $transient_orig ? delete_transient( Atualizador::TRANSIENT ) : set_transient( Atualizador::TRANSIENT, $transient_orig, Atualizador::VALIDADE );

echo "\n== Limpeza ==\n";
if ( ! $padrao_antes && $padrao && ! Midia::eh_imagem_padrao( $padrao_antes ) ) {
	delete_post_meta( $padrao, Midia::META_IMAGEM_PADRAO );
	wp_delete_attachment( $padrao, true );
	delete_option( Config::OPCAO_IMAGEM_ID );
	echo "Imagem padrão criada pelo teste removida.\n";
}
null === $hash_orig ? delete_option( Config::OPCAO_CHAVE_HASH ) : update_option( Config::OPCAO_CHAVE_HASH, $hash_orig, false );
echo "Chave temporária removida.\n";
null === $receb_orig ? delete_option( Config::OPCAO_RECEBIMENTO ) : update_option( Config::OPCAO_RECEBIMENTO, $receb_orig, false );
null === $ignorad_orig ? delete_option( Config::OPCAO_CAMPOS_IGNORADOS ) : update_option( Config::OPCAO_CAMPOS_IGNORADOS, $ignorad_orig, false );
echo "Recebimento e lista de campos ignorados restaurados.\n";

printf( "\nResultado: %d ok, %d falha(s)\n", $GLOBALS['cedu_inscricoes_t'][0], $GLOBALS['cedu_inscricoes_t'][1] );
