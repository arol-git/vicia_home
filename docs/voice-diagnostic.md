# 🔍 Guide de Diagnostic - Assistante Vocale

## TL;DR - Étapes Rapides

1. **Ouvre cette URL** → `http://votre-domaine.com/diagnostic-voice.html`
2. **Clique sur "Tester /api/v1/test"** → Si c'est vert, l'API répond
3. **Clique sur "Charger les logs"** → Tu verras exactement où ça casse
4. **Consulte les logs** → `tail -f storage/logs/api-voice.log` en SSH/terminal

---

## Symptômes et Causes Probables

### 🔴 Sur téléphone : "Page introuvable"
**Cause probable** : Routage API cassé (le serveur redirige vers le web router au lieu de l'API).

**À vérifier** :
1. Ton serveur Apache a-t-il `mod_rewrite` activé ?
2. Est-ce que `DocumentRoot` pointe vers `public/` ou vers la racine du projet ?
3. Vérifie les logs Apache/PHP pour voir la vraie requête

### 🟡 Sur Firefox : "Web Speech API non compatible"
**C'est normal** → Firefox nécessite un flag spécial ou une extension pour la reconnaissance vocale.

**Solution** : Utilise Chrome/Chromium/Edge.

### 🟡 Sur Chromium : "Vérifiez votre connexion"
**Cause probable** : La requête arrive au serveur mais l'authentification échoue OU l'API échoue.

**À vérifier** :
1. Ouvre les DevTools (F12) → Onglet Network
2. Cherche la requête vers `/api/v1/voice/command`
3. Regarde le statut HTTP (200 = OK, 401 = auth failed, 404 = not found, 500 = server error)
4. Lis la réponse JSON pour voir le message d'erreur

---

## Processus de Diagnostic Complet

### Étape 1 : Vérifier que l'API existe

```bash
# Sur le serveur, vérifier que le fichier existe
ls -la api/v1/voice.php
```

Doit afficher :
```
-rw-r--r-- 1 www-data www-data XXXX ... api/v1/voice.php
```

### Étape 2 : Vérifier que l'endpoint simple `/api/v1/test` répond

**Dans le navigateur** :
```
Aller à : http://votre-domaine.com/api/v1/test
```

**Réponse attendue (JSON)** :
```json
{
  "success": true,
  "message": "Test endpoint works!",
  "method": "GET",
  "session_user_id": 1
}
```

**Si tu reçois "Page introuvable"** :
- L'API n'est pas accessible
- Passe à l'Étape 3

### Étape 3 : Vérifier le routage Apache

```bash
# 1. Sur le serveur, vérifier que mod_rewrite est activé
apache2ctl -M | grep rewrite
```

Doit afficher : `rewrite_module (shared)`

```bash
# 2. Vérifier que le .htaccess est bien placé
ls -la .htaccess public/.htaccess
```

```bash
# 3. Vérifier les logs Apache
tail -f /var/log/apache2/error.log

# Chercher des messages comme:
# - RewriteRule not found
# - [rewrite:error] 
```

### Étape 4 : Vérifier la configuration du DocumentRoot

```bash
# Trouver le DocumentRoot d'Apache
grep -r "DocumentRoot" /etc/apache2/sites-enabled/

# Doit être SOIT:
# DocumentRoot /path/to/vicia-home/public
# OU
# DocumentRoot /path/to/vicia-home
```

**Si DocumentRoot = racine/**
→ Vérifie que le `.htaccess` racine redirige bien `/api/` vers `api/index.php`

**Si DocumentRoot = public/**
→ Vérifie que `public/.htaccess` redirige bien `/api/` vers `../api/index.php`

### Étape 5 : Tester la requête API complète

**Option A : Avec curl (terminal)**
```bash
curl -X POST http://votre-domaine.com/api/v1/voice/command \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=..." \
  -d '{"command": "test", "house_id": 1}'
```

**Option B : Avec le formulaire diagnostic (navigateur)**
- Va à `http://votre-domaine.com/diagnostic-voice.html`
- Clique sur "Tester /api/v1/voice/command"

**Réponse attendue** (si tu es connecté et tu as une maison) :
```json
{
  "success": false,
  "message": "Équipement non reconnu dans la commande"
}
```

### Étape 6 : Consulter les logs détaillés

**Sur le serveur, en SSH/terminal** :
```bash
# Voir les 50 dernières lignes des logs
tail -50 storage/logs/api-voice.log

# Suivre les logs en temps réel
tail -f storage/logs/api-voice.log
```

**Exemple de log complet réussi** :
```
=== REQUEST 2026-08-13 10:30:45 ===
METHOD: POST
REQUEST_URI: /api/v1/voice/command
PARSED URI: api/v1/voice/command
SEGMENTS (before filter): ["api","v1","voice","command"]
PARSED: version=v1, resource=voice, id=command, subaction=null, method=POST
ROUTE_FILE: /path/to/api/v1/voice.php (exists: YES)
HANDLER_FUNCTION: handle_voice (exists: YES)
Calling handler: handle_voice
[voice] HANDLER CALLED: method=POST, id=command, subaction=null
[voice] Attempting authentication...
[voice] Authentication SUCCESS: user_id=1, email=user@example.com
[voice] Input received: {"command":"allume la lumière","house_id":1}
[voice] House authorized: house_id=1
[voice] Processing voice command...
[handleVoiceCommand] START: command='allume la lumière', house_id=1
[handleVoiceCommand] Parsing command...
[handleVoiceCommand] Parse result: {"success":true,"intent":"on","room_name":"Salon","equipment_name":"Lumière",...}
[handleVoiceCommand] Looking up equipment ID=42...
[handleVoiceCommand] Equipment found: Lumière (ID=42)
[handleVoiceCommand] Verifying house ownership...
[handleVoiceCommand] Intent='on', setting state=1
[handleVoiceCommand] Equipment state updated in DB
[handleVoiceCommand] Publishing MQTT: topic=home/salon/light/set, payload=1
[handleVoiceCommand] MQTT published successfully
[handleVoiceCommand] SUCCESS: Responding with success
Handler returned normally
```

**Si tu vois une erreur** :
- Cherche `[ERROR]` dans les logs
- Lis le message après le `ERROR:` pour connaître le problème exact

---

## Troubleshooting Courant

### "Page introuvable" sur tous les appareils

**Possible solution 1** : mod_rewrite n'est pas activé
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

**Possible solution 2** : DocumentRoot n'est pas `public/`
```bash
# Vérifier et corriger dans Apache config
sudo nano /etc/apache2/sites-enabled/vicia-home.conf

# Doit contenir:
# DocumentRoot /path/to/vicia_home/public
```

**Possible solution 3** : .htaccess est bloqué dans Apache
```bash
# Dans /etc/apache2/sites-enabled/vicia-home.conf, ajouter:
<Directory /path/to/vicia_home/public>
    AllowOverride All
</Directory>
```

### "Erreur: Microphone non disponible" (Firefox/Safari)

**C'est normal** → La Web Speech API est principalement supportée par Chrome/Chromium/Edge.

**Solution** : Utilise Chrome ou Edge pour les tests vocaux.

### "Erreur: Vérifiez votre connexion" (Chromium)

**Vérifications** :
1. F12 → Network → Cherche `/api/v1/voice/command`
2. Regarde le statut HTTP
3. Lis la réponse JSON pour le message d'erreur
4. Vérifie les logs serveur (`tail -f storage/logs/api-voice.log`)

**Si statut = 401 (Unauthorized)** :
- Tu n'es pas connecté
- Connecte-toi d'abord

**Si statut = 404 (Not Found)** :
- L'API ne répond pas
- Reviens à l'Étape 2/3

**Si statut = 500 (Server Error)** :
- Erreur PHP/Application
- Consulte les logs Apache et Vicia Home

---

## Questions Importantes à Te Poser

1. **Est-ce que le diagnostic page (`/diagnostic-voice.html`) charge ?**
   - Si OUI → L'app web fonctionne
   - Si NON → Problème de serveur web global

2. **Est-ce que l'endpoint test (`/api/v1/test`) répond ?**
   - Si OUI → L'API fonctionne
   - Si NON → Problème de routage Apache

3. **Est-ce que tu es connecté à Vicia Home ?**
   - Si NON → Connecte-toi d'abord
   - Si OUI → Continue

4. **Quel navigateur tu utilises ?**
   - Firefox → Web Speech API pas supportée (utilise Chrome)
   - Chrome/Edge → Doit fonctionner
   - Safari → Web Speech API limité

5. **Est-ce que tes équipements existent ?**
   - Va à `/ai` ou `/houses` pour vérifier
   - Tu dois avoir au moins une maison et un équipement

---

## Contacter le Support

Si tu es bloqué(e), fournis :
1. La sortie de `tail -50 storage/logs/api-voice.log`
2. Le statut HTTP que tu reçois dans DevTools Network
3. La réponse JSON complète
4. Le navigateur et OS que tu utilises
5. La configuration de ton serveur (DocumentRoot, mod_rewrite actif ?)
