#!/bin/bash
# Script de configuration du bot Telegram

echo "=========================================="
echo "  🤖 Configuration du Bot Telegram"
echo "  Vicia Home - Environnement Virtuel"
echo "=========================================="

cd /opt/lampp/htdocs/vicia-home

# 1. Installer python3-venv
echo "1. Installation de python3-venv..."
sudo apt install python3-venv python3-full -y

# 2. Créer l'environnement virtuel
echo "2. Création de l'environnement virtuel..."
python3 -m venv venv

# 3. Activer l'environnement et installer les dépendances
echo "3. Installation des dépendances..."
source venv/bin/activate
pip install --upgrade pip
pip install mysql-connector-python python-telegram-bot paho-mqtt python-dotenv requests

# 4. Créer un script de lancement
echo "4. Création du script de lancement..."
cat > start_bot.sh << 'EOF'
#!/bin/bash
cd /opt/lampp/htdocs/vicia-home
source venv/bin/activate
python3 telegram/bot.py
EOF

chmod +x start_bot.sh

echo "=========================================="
echo "  ✅ Configuration terminée !"
echo ""
echo "  Pour lancer le bot :"
echo "  cd /opt/lampp/htdocs/vicia-home"
echo "  ./start_bot.sh"
echo ""
echo "  OU"
echo "  source venv/bin/activate"
echo "  python3 telegram/bot.py"
echo "=========================================="
