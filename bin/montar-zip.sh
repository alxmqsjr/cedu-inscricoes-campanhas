#!/usr/bin/env bash
# Monta o .zip do plugin para instalar ou atualizar à mão no site
# (Plugins → Adicionar novo → Enviar plugin, ou por FTP).
#
# Uso, na raiz do repositório (Git Bash):  bash bin/montar-zip.sh
# Resultado: dist/cedu-inscricoes-campanhas-X.Y.Z.zip, com a pasta interna
# "cedu-inscricoes-campanhas/" e sem os arquivos marcados como export-ignore
# no .gitattributes (tests/, bin/, README.md…).
set -euo pipefail
cd "$(dirname "$0")/.."

SLUG="cedu-inscricoes-campanhas"
PRINCIPAL="$SLUG.php"

cabecalho=$(sed -n 's/^ \* Version: *//p' "$PRINCIPAL" | tr -d '\r ')
constante=$(sed -n "s/^const VERSAO *= *'\([^']*\)'.*/\1/p" "$PRINCIPAL" | tr -d '\r')
estavel=$(sed -n 's/^Stable tag: *//p' readme.txt | tr -d '\r ')

if [[ -z "$cabecalho" || "$cabecalho" != "$constante" || "$cabecalho" != "$estavel" ]]; then
	echo "Versões diferentes: cabeçalho=$cabecalho, VERSAO=$constante, readme Stable tag=$estavel" >&2
	exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
	echo "Há alterações não commitadas: o zip sai do último commit (HEAD)." >&2
	exit 1
fi

mkdir -p dist
destino="dist/$SLUG-$cabecalho.zip"
git archive --format=zip --prefix="$SLUG/" -o "$destino" HEAD

echo "Gerado: $destino"
