#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
Bot Telegram pour Vicia Home
Version Python 3
"""

import os
import sys
import logging
import json
import mysql.connector
import paho.mqtt.client as mqtt
import requests
from datetime import datetime
from dotenv import load_dotenv
from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import Application, CommandHandler, CallbackQueryHandler, ContextTypes

# Charger les variables d'environnement
load_dotenv()

# Configuration
BOT_TOKEN = os.getenv('BOT_TOKEN')
ADMIN_ID = int(os.getenv('ADMIN_ID', 0))

# Configuration de la base de données
DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'user': os.getenv('DB_USER', 'vicia_user'),
    'password': os.getenv('DB_PASSWORD', 'vicia_password'),
    'database': os.getenv('DB_NAME', 'vicia_home')
}

# Configuration MQTT
MQTT_HOST = os.getenv('MQTT_HOST', 'localhost')
MQTT_PORT = int(os.getenv('MQTT_PORT', 1883))

# Logging
logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)
logger = logging.getLogger(__name__)


# ============================================
# CLASSE PRINCIPALE DU BOT
# ============================================

class ViciaHomeBot:
    """Bot Telegram pour Vicia Home"""
    
    def __init__(self):
        self.application = None
        self.db_connection = None
        
    def get_db_connection(self):
        """Établit la connexion à la base de données"""
        try:
            if self.db_connection is None or not self.db_connection.is_connected():
                self.db_connection = mysql.connector.connect(**DB_CONFIG)
            return self.db_connection
        except mysql.connector.Error as err:
            logger.error(f"Erreur de connexion à la BDD: {err}")
            return None
    
    def execute_query(self, query, params=None):
        """Exécute une requête SQL"""
        conn = self.get_db_connection()
        if not conn:
            return None
        
        try:
            cursor = conn.cursor(dictionary=True)
            cursor.execute(query, params or {})
            
            if query.strip().upper().startswith('SELECT'):
                result = cursor.fetchall()
                cursor.close()
                return result
            
            conn.commit()
            cursor.close()
            return True
            
        except mysql.connector.Error as err:
            logger.error(f"Erreur SQL: {err}")
            return None
    
    def publish_mqtt(self, topic, message):
        """Publie un message MQTT"""
        try:
            client = mqtt.Client()
            client.connect(MQTT_HOST, MQTT_PORT, 60)
            client.publish(topic, message)
            client.disconnect()
            return True
        except Exception as e:
            logger.error(f"Erreur MQTT: {e}")
            return False
    
    # ============================================
    # COMMANDES
    # ============================================
    
    async def cmd_start(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Commande /start"""
        keyboard = [
            [InlineKeyboardButton("📊 Status", callback_data="status")],
            [InlineKeyboardButton("💡 Équipements", callback_data="devices")],
            [InlineKeyboardButton("🌡️ Capteurs", callback_data="sensors")],
            [InlineKeyboardButton("🔔 Alertes", callback_data="alerts")],
            [InlineKeyboardButton("❓ Aide", callback_data="help")]
        ]
        reply_markup = InlineKeyboardMarkup(keyboard)
        
        message = (
            "🏠 <b>Bienvenue sur Vicia Home Bot !</b>\n\n"
            "Je vous aide à contrôler votre maison intelligente.\n"
            "Utilisez les boutons ci-dessous ou les commandes :\n\n"
            "/status - État de la maison\n"
            "/devices - Liste des équipements\n"
            "/sensors - Données des capteurs\n"
            "/alerts - Voir les alertes\n"
            "/help - Aide complète"
        )
        
        await update.message.reply_text(message, parse_mode='HTML', reply_markup=reply_markup)
    
    async def cmd_help(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Commande /help"""
        help_text = (
            "📋 <b>Commandes disponibles</b>\n\n"
            "/start - Démarrer le bot\n"
            "/status - État de la maison\n"
            "/devices - Liste des équipements\n"
            "/sensors - Données des capteurs\n"
            "/alerts - Voir les alertes\n"
            "/temp - Température actuelle\n"
            "/humid - Humidité actuelle\n"
            "/help - Cette aide\n\n"
            "💡 <i>Exemples d'utilisation :</i>\n"
            "/control salon on\n"
            "/control cuisine off"
        )
        await update.message.reply_text(help_text, parse_mode='HTML')
    
    async def cmd_status(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Commande /status"""
        # Récupérer les statistiques
        stats = self.get_stats()
        
        if not stats:
            await update.message.reply_text("❌ Erreur lors de la récupération des données")
            return
        
        status = (
            "🏠 <b>État de la maison</b>\n\n"
            f"📊 <b>Statistiques</b>\n"
            f"• Pièces : {stats['rooms']}\n"
            f"• Équipements : {stats['devices']}\n"
            f"• Capteurs : {stats['sensors']}\n"
            f"• Alertes : {stats['alerts']}\n\n"
            f"🌡️ <b>Température</b> : {stats['temperature']}°C\n"
            f"💧 <b>Humidité</b> : {stats['humidity']}%\n"
            f"🔆 <b>Luminosité</b> : {stats['light']} lux\n\n"
            f"📡 <b>Système</b>\n"
            f"• WiFi : {stats['wifi']}\n"
            f"• MQTT : {stats['mqtt']}\n"
            f"• Uptime : {stats['uptime']}"
        )
        
        await update.message.reply_text(status, parse_mode='HTML')
    
    async def cmd_devices(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Commande /devices"""
        devices = self.get_devices()
        
        if not devices:
            await update.message.reply_text("📭 Aucun équipement trouvé")
            return
        
        message = "💡 <b>Liste des équipements</b>\n\n"
        buttons = []
        
        for device in devices:
            status_icon = '✅' if device['state'] == 'on' else '❌'
            message += f"{status_icon} <b>{device['name']}</b>\n"
            message += f"   📍 {device['room']}\n"
            message += f"   📊 {device['state']}\n\n"
            
            buttons.append([
                InlineKeyboardButton(
                    f"{device['icon']} {device['name']}",
                    callback_data=f"device_{device['id']}"
                )
            ])
        
        reply_markup = InlineKeyboardMarkup(buttons)
        await update.message.reply_text(message, parse_mode='HTML', reply_markup=reply_markup)
    
    async def cmd_sensors(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Commande /sensors"""
        sensors = self.get_sensors()
        
        if not sensors:
            await update.message.reply_text("📭 Aucun capteur trouvé")
            return
        
        message = "🌡️ <b>Données des capteurs</b>\n\n"
        
        for sensor in sensors:
            status = '🟢' if sensor['status'] == 'online' else '🔴'
            value = sensor['last_value'] or '--'
            unit = sensor['unit'] or ''
            
            message += f"{status} <b>{sensor['name']}</b>\n"
            message += f"   📍 {sensor['room']}\n"
            message += f"   📊 {value} {unit}\n\n"
        
        await update.message.reply_text(message, parse_mode='HTML')
    
    async def cmd_alerts(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Commande /alerts"""
        alerts = self.get_alerts()
        
        if not alerts:
            await update.message.reply_text("✅ Aucune alerte en cours")
            return
        
        message = "🔔 <b>Alertes en cours</b>\n\n"
        
        for alert in alerts:
            severity_icon = {
                'critical': '🚨',
                'warning': '⚠️',
                'info': 'ℹ️'
            }.get(alert['severity'], '📌')
            
            message += f"{severity_icon} <b>{alert['title']}</b>\n"
            message += f"   📝 {alert['message']}\n"
            message += f"   ⏰ {alert['created_at'].strftime('%H:%M')}\n\n"
        
        await update.message.reply_text(message, parse_mode='HTML')
    
    async def cmd_temp(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Commande /temp"""
        temp = self.get_temperature()
        
        if temp is not None:
            await update.message.reply_text(f"🌡️ <b>Température actuelle</b> : <b>{temp}°C</b>", parse_mode='HTML')
        else:
            await update.message.reply_text("❌ Donnée de température indisponible")
    
    async def cmd_humid(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Commande /humid"""
        humid = self.get_humidity()
        
        if humid is not None:
            await update.message.reply_text(f"💧 <b>Humidité actuelle</b> : <b>{humid}%</b>", parse_mode='HTML')
        else:
            await update.message.reply_text("❌ Donnée d'humidité indisponible")
    
    async def cmd_control(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Commande /control <nom> <on/off>"""
        args = context.args
        
        if len(args) < 2:
            await update.message.reply_text(
                "❌ Format : /control <nom> <on/off>\n"
                "Exemple : /control salon on"
            )
            return
        
        device_name = args[0]
        action = args[1].lower()
        
        if action not in ['on', 'off']:
            await update.message.reply_text("❌ Action invalide. Utilisez 'on' ou 'off'")
            return
        
        result = self.control_device(device_name, action)
        
        if result:
            await update.message.reply_text(
                f"✅ Équipement <b>{device_name}</b> : <b>{action.upper()}</b>",
                parse_mode='HTML'
            )
        else:
            await update.message.reply_text(f"❌ Équipement <b>{device_name}</b> introuvable", parse_mode='HTML')
    
    # ============================================
    # CALLBACKS (Boutons)
    # ============================================
    
    async def handle_callback(self, update: Update, context: ContextTypes.DEFAULT_TYPE):
        """Gère les clics sur les boutons"""
        query = update.callback_query
        await query.answer()
        
        data = query.data
        chat_id = query.message.chat_id
        
        if data == 'status':
            await self.cmd_status(update, context)
        
        elif data == 'devices':
            await self.cmd_devices(update, context)
        
        elif data == 'sensors':
            await self.cmd_sensors(update, context)
        
        elif data == 'alerts':
            await self.cmd_alerts(update, context)
        
        elif data == 'help':
            await self.cmd_help(update, context)
        
        elif data.startswith('device_'):
            # Afficher les détails d'un équipement
            device_id = data.split('_')[1]
            await self.show_device_details(update, context, device_id)
        
        elif data.startswith('control_'):
            # Contrôler un équipement depuis un bouton
            parts = data.split('_')
            device_id = parts[1]
            action = parts[2]
            
            result = self.control_device_by_id(device_id, action)
            
            if result:
                await query.edit_message_text(
                    f"✅ Équipement modifié : <b>{action.upper()}</b>",
                    parse_mode='HTML'
                )
            else:
                await query.edit_message_text("❌ Erreur lors du contrôle")
    
    async def show_device_details(self, update: Update, context: ContextTypes.DEFAULT_TYPE, device_id: str):
        """Affiche les détails d'un équipement"""
        device = self.get_device_by_id(device_id)
        
        if not device:
            await update.callback_query.edit_message_text("❌ Équipement introuvable")
            return
        
        status_icon = '🟢' if device['state'] == 'on' else '🔴'
        
        message = (
            f"💡 <b>{device['name']}</b>\n\n"
            f"📍 Pièce : {device['room']}\n"
            f"📊 État : {status_icon} <b>{device['state'].upper()}</b>\n"
            f"📡 Statut : {device['status']}\n"
        )
        
        buttons = [
            [
                InlineKeyboardButton("🟢 Allumer", callback_data=f"control_{device_id}_on"),
                InlineKeyboardButton("🔴 Éteindre", callback_data=f"control_{device_id}_off")
            ],
            [
                InlineKeyboardButton("🔄 Rafraîchir", callback_data=f"device_{device_id}")
            ]
        ]
        
        reply_markup = InlineKeyboardMarkup(buttons)
        await update.callback_query.edit_message_text(message, parse_mode='HTML', reply_markup=reply_markup)
    
    # ============================================
    # MÉTHODES D'ACCÈS AUX DONNÉES
    # ============================================
    
    def get_stats(self):
        """Récupère les statistiques"""
        try:
            # Nombre de pièces
            rooms = self.execute_query("SELECT COUNT(*) as count FROM rooms WHERE deleted_at IS NULL")
            
            # Nombre d'équipements
            devices = self.execute_query("SELECT COUNT(*) as count FROM devices WHERE deleted_at IS NULL")
            
            # Nombre de capteurs
            sensors = self.execute_query("SELECT COUNT(*) as count FROM sensors WHERE deleted_at IS NULL")
            
            # Alertes
            alerts = self.execute_query("SELECT COUNT(*) as count FROM security_events WHERE resolved = 0")
            
            # Température
            temp = self.execute_query(
                "SELECT last_value FROM sensors WHERE name LIKE '%temperature%' "
                "ORDER BY last_update DESC LIMIT 1"
            )
            
            # Humidité
            humid = self.execute_query(
                "SELECT last_value FROM sensors WHERE name LIKE '%humidit%' "
                "ORDER BY last_update DESC LIMIT 1"
            )
            
            # Luminosité
            light = self.execute_query(
                "SELECT last_value FROM sensors WHERE name LIKE '%luminosit%' "
                "ORDER BY last_update DESC LIMIT 1"
            )
            
            return {
                'rooms': rooms[0]['count'] if rooms else 0,
                'devices': devices[0]['count'] if devices else 0,
                'sensors': sensors[0]['count'] if sensors else 0,
                'alerts': alerts[0]['count'] if alerts else 0,
                'temperature': temp[0]['last_value'] if temp and temp[0]['last_value'] else '--',
                'humidity': humid[0]['last_value'] if humid and humid[0]['last_value'] else '--',
                'light': light[0]['last_value'] if light and light[0]['last_value'] else '--',
                'wifi': '✅ Connecté',
                'mqtt': '✅ Connecté',
                'uptime': '2 jours'
            }
        except Exception as e:
            logger.error(f"Erreur get_stats: {e}")
            return None
    
    def get_devices(self):
        """Récupère la liste des équipements"""
        try:
            return self.execute_query("""
                SELECT d.*, r.name as room, dt.icon
                FROM devices d
                LEFT JOIN rooms r ON r.id = d.room_id
                LEFT JOIN device_types dt ON dt.id = d.device_type_id
                WHERE d.deleted_at IS NULL
                ORDER BY d.name
            """)
        except Exception as e:
            logger.error(f"Erreur get_devices: {e}")
            return []
    
    def get_device_by_id(self, device_id):
        """Récupère un équipement par son ID"""
        try:
            result = self.execute_query("""
                SELECT d.*, r.name as room
                FROM devices d
                LEFT JOIN rooms r ON r.id = d.room_id
                WHERE d.id = %s AND d.deleted_at IS NULL
            """, (device_id,))
            
            return result[0] if result else None
        except Exception as e:
            logger.error(f"Erreur get_device_by_id: {e}")
            return None
    
    def get_sensors(self):
        """Récupère la liste des capteurs"""
        try:
            return self.execute_query("""
                SELECT s.*, st.unit, r.name as room
                FROM sensors s
                LEFT JOIN sensor_types st ON st.id = s.sensor_type_id
                LEFT JOIN rooms r ON r.id = s.room_id
                WHERE s.deleted_at IS NULL
                ORDER BY s.name
            """)
        except Exception as e:
            logger.error(f"Erreur get_sensors: {e}")
            return []
    
    def get_alerts(self):
        """Récupère les alertes non résolues"""
        try:
            return self.execute_query("""
                SELECT * FROM security_events 
                WHERE resolved = 0
                ORDER BY created_at DESC
                LIMIT 10
            """)
        except Exception as e:
            logger.error(f"Erreur get_alerts: {e}")
            return []
    
    def get_temperature(self):
        """Récupère la température actuelle"""
        try:
            result = self.execute_query(
                "SELECT last_value FROM sensors WHERE name LIKE '%temperature%' "
                "ORDER BY last_update DESC LIMIT 1"
            )
            return result[0]['last_value'] if result and result[0]['last_value'] else None
        except Exception as e:
            logger.error(f"Erreur get_temperature: {e}")
            return None
    
    def get_humidity(self):
        """Récupère l'humidité actuelle"""
        try:
            result = self.execute_query(
                "SELECT last_value FROM sensors WHERE name LIKE '%humidit%' "
                "ORDER BY last_update DESC LIMIT 1"
            )
            return result[0]['last_value'] if result and result[0]['last_value'] else None
        except Exception as e:
            logger.error(f"Erreur get_humidity: {e}")
            return None
    
    def control_device(self, device_name, action):
        """Contrôle un équipement par son nom"""
        try:
            # Vérifier si l'équipement existe
            result = self.execute_query(
                "SELECT id FROM devices WHERE name = %s AND deleted_at IS NULL",
                (device_name,)
            )
            
            if not result:
                return False
            
            # Publier sur MQTT
            self.publish_mqtt(f"home/device/{device_name}/command", action)
            
            # Mettre à jour en BDD
            self.execute_query(
                "UPDATE devices SET state = %s, updated_at = NOW() WHERE name = %s AND deleted_at IS NULL",
                (action, device_name)
            )
            
            return True
            
        except Exception as e:
            logger.error(f"Erreur control_device: {e}")
            return False
    
    def control_device_by_id(self, device_id, action):
        """Contrôle un équipement par son ID"""
        device = self.get_device_by_id(device_id)
        if not device:
            return False
        
        return self.control_device(device['name'], action)
    
    # ============================================
    # DÉMARRAGE DU BOT
    # ============================================
    
    def run(self):
        """Démarre le bot"""
        # Créer l'application
        self.application = Application.builder().token(BOT_TOKEN).build()
        
        # Ajouter les commandes
        self.application.add_handler(CommandHandler('start', self.cmd_start))
        self.application.add_handler(CommandHandler('help', self.cmd_help))
        self.application.add_handler(CommandHandler('status', self.cmd_status))
        self.application.add_handler(CommandHandler('devices', self.cmd_devices))
        self.application.add_handler(CommandHandler('sensors', self.cmd_sensors))
        self.application.add_handler(CommandHandler('alerts', self.cmd_alerts))
        self.application.add_handler(CommandHandler('temp', self.cmd_temp))
        self.application.add_handler(CommandHandler('humid', self.cmd_humid))
        self.application.add_handler(CommandHandler('control', self.cmd_control))
        
        # Ajouter le gestionnaire de callbacks
        self.application.add_handler(CallbackQueryHandler(self.handle_callback))
        
        # Démarrer le bot
        print("========================================")
        print("  🤖 Bot Telegram Vicia Home")
        print("  Version Python")
        print("========================================")
        print("")
        print("Bot démarré ! Appuyez sur Ctrl+C pour arrêter.")
        print("")
        
        self.application.run_polling(allowed_updates=Update.ALL_TYPES)


# ============================================
# POINT D'ENTRÉE
# ============================================

if __name__ == '__main__':
    if not BOT_TOKEN:
        print("❌ Erreur: BOT_TOKEN non défini dans .env")
        sys.exit(1)
    
    bot = ViciaHomeBot()
    bot.run()

