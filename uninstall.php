<?php
/**
 * Desinstalação: remove só as configurações do plugin.
 *
 * As inscrições, as metas e a mídia NÃO são apagadas: o CPT pertence ao
 * tema e os dados continuam sendo exibidos no site.
 *
 * @package CemadenInscricoes
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$cedu_inscricoes_opcoes = array(
	'cedu_inscricoes_api_key_hash',
	'cedu_inscricoes_recebimento',
	'cedu_inscricoes_mensagem_fechado',
	'cedu_inscricoes_edicao_padrao',
	'cedu_inscricoes_autor_id',
	'cedu_inscricoes_imagem_padrao_url',
	'cedu_inscricoes_imagem_padrao_id',
	'cedu_inscricoes_campos_ignorados',
);
foreach ( $cedu_inscricoes_opcoes as $cedu_inscricoes_opcao ) {
	delete_option( $cedu_inscricoes_opcao );
}

delete_metadata( 'user', 0, 'cedu_inscricoes_por_pagina', '', true );
delete_transient( 'cedu_inscricoes_release' );

// Travas de importação esquecidas (processo interrompido no meio).
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$cedu_inscricoes_travas = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'cedu_inscricoes_trava_' ) . '%' ) );
foreach ( $cedu_inscricoes_travas as $cedu_inscricoes_opcao ) {
	delete_option( $cedu_inscricoes_opcao );
}
