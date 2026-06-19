#!/usr/bin/env bash
# Downloads all Kerala Sahodaya websites into named folders

BASE_DIR="$(cd "$(dirname "$0")" && pwd)"
WGET=/opt/homebrew/bin/wget

download_site() {
  local NAME="$1"
  local URL="$2"
  local FOLDER="$BASE_DIR/$NAME"
  mkdir -p "$FOLDER"

  echo ""
  echo "=========================================="
  echo "Downloading: $NAME"
  echo "URL: $URL"
  echo "Into: $FOLDER"
  echo "=========================================="

  $WGET \
    --mirror \
    --convert-links \
    --adjust-extension \
    --page-requisites \
    --no-parent \
    --wait=1 \
    --random-wait \
    --user-agent="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36" \
    --directory-prefix="$FOLDER" \
    --timeout=30 \
    --tries=3 \
    --no-check-certificate \
    "$URL" 2>&1 | grep -E "(ERROR|WARNING|Downloaded|FINISHED|saved)" || true

  echo "Done: $NAME"
}

rm -rf "$BASE_DIR/0" 2>/dev/null || true

download_site "Kannur_Sahodaya"              "https://www.kannursahodaya.in/"
download_site "Central_Kerala_Sahodaya"      "https://centralkeralasahodaya.com/"
download_site "Central_Travancore_Sahodaya"  "https://www.travancoresahodaya.in/"
download_site "Thrissur_Central_Sahodaya"    "https://thrissurcentralsahodaya.com/"
download_site "Thrissur_Sahodaya"            "https://sahodayathrissur.com/"
download_site "Capital_District_Sahodaya"    "http://www.capitaldistrictsahodaya.in/"
download_site "Alappuzha_Sahodaya"           "http://alappuzhasahodaya.org/"
download_site "Malappuram_Sahodaya"          "https://malappuramsahodaya.weebly.com/"
download_site "Confederation_Kerala_Sahodaya" "https://www.confedsahodaya.com/"
download_site "Kerala_State_CBSE_Kalotsav"   "https://www.keralastatecbsekalotsav.in/"

echo ""
echo "=========================================="
echo "All downloads complete!"
du -sh "$BASE_DIR"/*/
echo "=========================================="
