=== Cemaden Educação: Inscrições de Campanhas ===
Contributors: alxmqsjr
Tags: inscrições, formulário, rest api, gerenciador
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recebe as inscrições das campanhas de um formulário externo (JSON via REST) e gerencia o CPT "inscricoes".

== Description ==

* Endpoint `POST /wp-json/cedu/v1/inscricao`, autenticado pelo cabeçalho `X-CEDU-API-KEY`.
* Recebimento que pode ser aberto e fechado nas Configurações.
* Campos do formulário configurados num único arquivo, `config/campos.php`. Veja `docs/CAMPOS.md`.
* Cria ou atualiza a inscrição pelo identificador do envio.
* Baixa a capa e as imagens públicas para a Biblioteca de Mídia. As evidências internas ficam só como URL e legenda.
* Inscrições que não autorizam expor as imagens usam uma imagem padrão única, compartilhada.
* Atribui a edição configurada (taxonomia `edicao_inscricao`) às inscrições novas.
* Tela "Gerenciar": busca, filtros, possíveis duplicadas e exclusão definitiva em lotes, com a mídia de cada inscrição.
* Tela "Mídias": consulta por origem, somente leitura.
* Tela "Informações": situação atual, formato do JSON, campos não configurados e tutorial para novas edições.
* Comandos WP-CLI: `wp inscricoes importar|listar|apagar|chave`.

O CPT `inscricoes` e as taxonomias `status_inscricao` e `edicao_inscricao` são registrados pelo tema. O plugin só os registra se ninguém o tiver feito.

= Chave da API =

Nenhuma chave vem no código. Configure uma das opções:

1. No `wp-config.php` (recomendado): `define( 'CEDU_INSCRICOES_API_KEY', 'sua-chave' );`
2. Em Inscrições → Configurações. O banco guarda só o hash SHA-256.

Sem chave configurada, o endpoint responde 503 a todos os envios.

== Installation ==

1. Envie a pasta `cedu-inscricoes-campanhas` para `wp-content/plugins/` e ative o plugin.
2. Configure a chave da API (ver acima).
3. Confira a edição e o recebimento em Inscrições → Configurações.

== Changelog ==

= 1.0.0 =
* Primeira versão: recebimento por API, gerenciador de inscrições, consulta de mídias, liga/desliga do recebimento, campos configuráveis em `config/campos.php` e tela de informações.
