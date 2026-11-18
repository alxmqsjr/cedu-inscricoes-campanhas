# Cemaden Educação: Inscrições de Campanhas

Plugin WordPress que recebe as inscrições das campanhas enviadas por um formulário externo (JSON via REST) e oferece um gerenciador para o CPT `inscricoes`.

- **Campos do formulário:** [`config/campos.php`](config/campos.php), com guia em [`docs/CAMPOS.md`](docs/CAMPOS.md). O mesmo guia aparece no painel, em *Inscrições → Informações → Guia dos campos*.
- **Tutorial de uso:** no painel, *Inscrições → Informações*.
- **Autor:** [Alexandre Marques](https://github.com/alxmqsjr/)

## Instalação

Baixe o `cedu-inscricoes-campanhas-X.Y.Z.zip` anexado à [release mais recente](https://github.com/alxmqsjr/cedu-inscricoes-campanhas/releases) (o arquivo anexado, não o "Source code"). Depois, em *Plugins → Adicionar novo → Enviar plugin*, envie o zip e ative.

## Atualização

Há duas formas. Nas duas, as configurações, a chave e as inscrições ficam no banco e não são afetadas.

- **Pelo painel (automática):** o plugin consulta as releases deste repositório no máximo a cada 12 horas. Quando há uma versão mais nova, ela aparece em *Plugins* com "Atualizar agora".
- **À mão:** baixe o zip da release, envie em *Plugins → Adicionar novo → Enviar plugin* e confirme **"Substituir o atual pelo enviado"**. Por FTP também funciona: substitua a pasta `wp-content/plugins/cedu-inscricoes-campanhas/`.

Para desligar as verificações automáticas num site: `define( 'CEDU_INSCRICOES_ATUALIZAR', false );` no `wp-config.php`.

> A atualização substitui todos os arquivos do plugin, inclusive `config/campos.php`. Por isso, mudanças nos campos devem ser feitas aqui no repositório e publicadas como nova versão.

O servidor precisa permitir que o PHP grave em `wp-content/plugins`. Se a atualização pelo painel falhar com "não foi possível copiar alguns arquivos", ajuste o dono e as permissões da pasta ou use `FS_METHOD` no `wp-config.php`.

### Publicando uma versão

1. Suba a versão nos três lugares: `Version:` no cabeçalho de `cedu-inscricoes-campanhas.php`, `const VERSAO` no mesmo arquivo e `Stable tag:` no `readme.txt`. Registre a mudança no changelog do `readme.txt`.
2. Faça o commit.
3. Rode `bash bin/montar-zip.sh`. O script confere as versões e gera `dist/cedu-inscricoes-campanhas-X.Y.Z.zip` a partir do último commit.
4. `git tag vX.Y.Z && git push origin main vX.Y.Z`.
5. Crie a release `vX.Y.Z` no GitHub (não marque como pre-release) e **anexe o `.zip`**. Sem o zip anexado, o painel não oferece a atualização. O texto da release aparece em "Ver detalhes" no painel.

## Chave da API

Nenhuma chave fica no código. Defina no `wp-config.php` do site:

```php
define( 'CEDU_INSCRICOES_API_KEY', 'COLE-A-CHAVE-AQUI' );
```

`COLE-A-CHAVE-AQUI` é só um marcador: troque pelo texto da chave, que deve ser igual à usada no formulário. A tela *Inscrições → Configurações* gera uma chave nova já na linha pronta para copiar. Como alternativa, a chave pode ser salva nessa tela, e o banco guarda só o hash.

## API

```
POST /wp-json/cedu/v1/inscricao
X-CEDU-API-KEY: <chave>
Content-Type: application/json
```

| Status | Quando |
|---|---|
| 201 | inscrição criada |
| 200 | inscrição atualizada (mesmo identificador) |
| 400 | corpo vazio, JSON inválido ou lista em vez de objeto |
| 401 | chave ausente ou inválida |
| 403 | recebimento fechado nas Configurações |
| 409 | o mesmo identificador está sendo processado por outro envio |
| 503 | nenhuma chave configurada |

Resposta: `{"message": "…", "post_id": 123}`.

## Estrutura

```
cedu-inscricoes-campanhas.php     cabeçalho, constantes e carga dos módulos
uninstall.php                     remove só as options do plugin
config/campos.php                 ← campos do formulário (único arquivo a editar)
docs/CAMPOS.md                    como adaptar o plugin a um formulário novo
includes/
  class-plugin.php                inicialização, ativação, hook de exclusão
  class-esquema.php               lê e valida config/campos.php
  class-config.php                options e valores padrão
  class-post-type.php             registro de reserva do CPT e das taxonomias
  class-importador.php            JSON → post + metas + mídia
  class-midia.php                 download, imagem padrão compartilhada, limpeza
  class-repositorio.php           consultas e exclusão
  class-cli.php                   wp inscricoes …
  class-atualizador.php           atualizações pelo painel (releases do GitHub)
  api/class-autenticacao.php      chave da API
  api/class-rest-controller.php   POST cedu/v1/inscricao
  api/class-rest-admin.php        rotas internas de exclusão em lotes
  admin/                          telas Gerenciar, Mídias, Configurações e Informações; colunas; guia
templates/admin/                  HTML das telas
assets/admin/                     CSS e JS do painel (sem etapa de build)
tests/smoke.php                   teste de ponta a ponta (wp eval-file)
bin/montar-zip.sh                 gera o .zip da release
```

## Hooks

| Filtro | Para quê |
|---|---|
| `cedu_inscricoes_campos` | alterar os campos sem editar o plugin (ver `docs/CAMPOS.md`) |
| `cedu_inscricoes_imagem_padrao_url` | trocar a URL da imagem padrão |

## WP-CLI

```
wp inscricoes importar inscricao.json
wp inscricoes listar --edicao=2026 --format=csv
wp inscricoes listar --duplicadas
wp inscricoes apagar 123 456
wp inscricoes apagar --todas --edicao=2026 --dry-run
wp inscricoes chave status
```

## Testes

```
wp eval-file wp-content/plugins/cedu-inscricoes-campanhas/tests/smoke.php
```

O teste cria inscrições (com download de imagens) e confere metas, reenvio, imagem padrão, recebimento fechado, campos não configurados, telas e exclusão. No fim, apaga tudo o que criou. Rode só em ambiente de desenvolvimento. O zip da release não leva a pasta `tests/`.

## Licença

GPL-2.0-or-later.
