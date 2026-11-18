<?php
/**
 * Plugin Name:       Cemaden Educação: Inscrições de Campanhas
 * Plugin URI:        https://github.com/alxmqsjr/cedu-inscricoes-campanhas
 * Description:       Recebe as inscrições das campanhas enviadas por um formulário externo (JSON via REST) e gerencia o CPT "inscricoes": listagem, filtros, exclusão em lotes com a mídia associada e consulta das mídias.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * Author:            Alexandre Marques
 * Author URI:        https://github.com/alxmqsjr/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cedu-inscricoes-campanhas
 * Domain Path:       /languages
 * Update URI:        https://github.com/alxmqsjr/cedu-inscricoes-campanhas
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;

const VERSAO  = '1.0.0';
const ARQUIVO = __FILE__;
const DIR     = __DIR__;
const DOMINIO = 'cedu-inscricoes-campanhas';

require_once DIR . '/includes/class-esquema.php';
require_once DIR . '/includes/class-config.php';
require_once DIR . '/includes/class-post-type.php';
require_once DIR . '/includes/class-midia.php';
require_once DIR . '/includes/class-repositorio.php';
require_once DIR . '/includes/class-importador.php';
require_once DIR . '/includes/api/class-autenticacao.php';
require_once DIR . '/includes/api/class-rest-controller.php';
require_once DIR . '/includes/api/class-rest-admin.php';
require_once DIR . '/includes/class-plugin.php';

add_action( 'plugins_loaded', array( Plugin::class, 'iniciar' ) );

register_activation_hook( ARQUIVO, array( Plugin::class, 'ativar' ) );
register_deactivation_hook( ARQUIVO, array( Plugin::class, 'desativar' ) );
