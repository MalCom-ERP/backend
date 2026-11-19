#!/bin/bash
set -e

# ==============================================================================
# Installation d'un GitHub Actions Self-Hosted Runner sur le VPS OVH (Ubuntu)
# ==============================================================================
# Prérequis sur GitHub :
#   MalCom-ERP -> Settings -> Actions -> Runners -> New self-hosted runner
#   Copiez le token généré (ex: AXXXX...) puis lancez ce script sur le VPS :
#     ./setup-vps-runner.sh <GITHUB_ORG_RUNNER_TOKEN>
# ==============================================================================

TOKEN="${1:-}"
ORG_URL="https://github.com/MalCom-ERP"
RUNNER_DIR="/opt/actions-runner"
RUNNER_VERSION="2.323.0"

if [ -z "$TOKEN" ]; then
  echo "Usage: $0 <GITHUB_ORG_RUNNER_TOKEN>"
  exit 1
fi

echo "🔍 Vérification de Node.js 20..."
if ! command -v node >/dev/null 2>&1 || [ "$(node -v | cut -d. -f1 | tr -d v)" -lt 20 ]; then
  echo "📦 Installation de Node.js 20 LTS..."
  curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
  sudo apt-get install -y nodejs
fi
node -v

echo "📦 Installation des dépendances utiles (rsync, jq, curl)..."
sudo apt-get update -y && sudo apt-get install -y rsync jq curl libicu-dev

echo "📥 Préparation du dossier ${RUNNER_DIR}..."
sudo mkdir -p "$RUNNER_DIR"
sudo chown -R "$USER:$USER" "$RUNNER_DIR"
cd "$RUNNER_DIR"

ARCHIVE="actions-runner-linux-x64-${RUNNER_VERSION}.tar.gz"
echo "⬇️ Téléchargement de GitHub Actions Runner v${RUNNER_VERSION}..."
curl -fsSL -o "$ARCHIVE" "https://github.com/actions/runner/releases/download/v${RUNNER_VERSION}/${ARCHIVE}"
tar xzf "$ARCHIVE"
rm -f "$ARCHIVE"

echo "⚙️ Configuration du runner pour l'organisation MalCom-ERP..."
./config.sh \
  --url "$ORG_URL" \
  --token "$TOKEN" \
  --name "vps-malcom-ubuntu" \
  --labels "self-hosted,linux,vps" \
  --unattended \
  --replace

echo "🚀 Installation et démarrage du service systemd..."
sudo ./svc.sh install
sudo ./svc.sh start
sudo ./svc.sh status

echo "✅ Runner self-hosted opérationnel sur le VPS !"
