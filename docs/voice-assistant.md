# Voice Assistant Testing Guide

## Overview

The voice assistant is a **fully independent module** separate from the existing AI system. It uses:
- **Backend**: `VoiceCommandService` for French voice command parsing
- **API**: `POST /api/v1/voice/command` for voice processing
- **Frontend**: Web Speech API with modal UI and animated waveform
- **Control**: MQTT publishing for equipment state changes

## Architecture

```
User Voice Input
    ↓
Web Speech API (browser)
    ↓
VoiceAssistant.js (handles UI + transcription)
    ↓
POST /api/v1/voice/command (JSON)
    ↓
api/v1/voice.php (route handler)
    ↓
VoiceCommandService::parse() (command recognition)
    ↓
Equipment::setState() + Publisher::publish()
    ↓
MQTT Message → ESP32 Device
```

## Files Created

### Backend Services
1. **app/services/VoiceCommandService.php** (new)
   - `parse($command, $houseId)` → analyzes French voice commands
   - Recognizes intents: "allume" (on), "éteins" (off), "bascule" (toggle)
   - Fuzzy matches equipment/room names in current house
   - Returns structured result with equipment ID and action

2. **api/v1/voice.php** (new)
   - Endpoint: `POST /api/v1/voice/command`
   - Authenticates via session OR bearer token
   - Validates equipment ownership
   - Executes state change + MQTT publish
   - Supports request: `{ "command": "allume la lumière du salon", "house_id": 1 }`

### Frontend UI
3. **public/assets/css/voice.css** (new)
   - Fixed button (FAB) in bottom-right corner
   - Modal with animated waveform indicator
   - State indicators (listening, processing, success, error)
   - Responsive design for mobile/desktop

4. **public/assets/js/voice.js** (new)
   - Web Speech API integration (fr-FR language)
   - Modal controls (open, close, mic button)
   - Fetch integration with session authentication
   - Real-time feedback and error handling

### Layout Integration
5. **app/views/layout/header.php** (modified)
   - Added: voice.css stylesheet
   - Added: Script to pass `window.__vicia_house_id` to frontend

6. **app/views/layout/footer.php** (modified)
   - Added: voice.js script loading

## How to Test

### 1. Prerequisites
- ✅ Running Vicia Home instance
- ✅ At least one house with equipment (lights, relays, etc.)
- ✅ Browser with Web Speech API support (Chrome, Edge, Firefox with flag)
- ✅ Microphone access enabled

### 2. Test Scenarios

#### Scenario 1: Basic Command
1. Navigate to any authenticated page (e.g., `/ai`)
2. Look for microphone button **bottom-right corner** 🎤
3. Click the button → modal opens "Assistante Vocale"
4. Click "Écouter" button
5. Say: **"allume la lumière du salon"** (turn on the living room light)
6. Modal shows: "Écoute en cours…" → "Traitement de la commande…" → "✅ Commande exécutée"
7. Equipment state changes to ON, MQTT message sent

#### Scenario 2: Turn Off
1. Click mic button, click "Écouter"
2. Say: **"éteins la porte"** (turn off the door)
3. Command executes, state changes to OFF

#### Scenario 3: Toggle
1. Click mic button, click "Écouter"
2. Say: **"bascule le ventilateur du grenier"** (toggle the attic fan)
3. State toggles based on current value

#### Scenario 4: Error Handling
- Say unclear words → "Intention non reconnue"
- Name non-existent equipment → "Équipement non reconnu"
- No microphone → "Erreur: Microphone non disponible"

### 3. Console Debugging

Open browser DevTools (F12), check Console for:
- ✅ VoiceAssistant initialization message (if added)
- Recognition events and transcription
- Fetch request/response logs
- Error messages

Check Network tab:
- `POST /api/v1/voice/command`
- Response format: `{ success: true, message: "...", equipment_id: X, new_state: 0/1 }`

### 4. Database Verification

Check that equipment state was actually changed:
```sql
SELECT id, name, state, mqtt_topic FROM equipments WHERE id = [tested_id];
```

Check MQTT logs:
```sql
SELECT topic, payload, created_at FROM mqtt_logs 
WHERE topic LIKE '%/set' 
ORDER BY created_at DESC LIMIT 5;
```

## French Commands Recognized

The service recognizes these patterns:

### Turn ON
- "Allume la lumière du salon"
- "Active le ventilateur"
- "Mets la pompe en route"
- "Ouvre la porte"

### Turn OFF
- "Éteins la lumière"
- "Désactive le relais"
- "Coupe la pompe"
- "Ferme la porte"
- "Arrête le moteur"

### Toggle
- "Bascule le climatiseur"
- "Inverse l'éclairage"
- "Toggle la sonnette"

## API Endpoint Reference

### Request
```json
POST /api/v1/voice/command
Content-Type: application/json

{
  "command": "allume la lumière du salon",
  "house_id": 1
}
```

### Response (Success)
```json
{
  "success": true,
  "message": "Activation de Lumière du salon",
  "equipment_id": 42,
  "equipment_name": "Lumière",
  "room_name": "Salon",
  "new_state": 1
}
```

### Response (Error)
```json
{
  "success": false,
  "message": "Équipement non reconnu dans la commande"
}
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| No mic button appears | Check browser console for JS errors; verify voice.js loaded |
| "Microphone non disponible" | Grant microphone permission; check browser settings |
| Commands not recognized | Ensure equipment names are in the database; try clearer pronunciation |
| MQTT not published | Verify Publisher.php is working; check mqtt/config.php; check logs |
| Session authentication fails | Ensure user is logged in; check session cookie in browser |
| Modal doesn't close after success | Check if closeModal() is being called (2-second timeout) |

## Design Decisions

1. **Independent Service**: `VoiceCommandService` has NO dependencies on AIService, LLMService, or IntentClassifier
2. **Dual Authentication**: API supports both session (web frontend) AND bearer tokens (mobile/third-party apps)
3. **French Language Only**: Web Speech API configured for `lang: 'fr-FR'`
4. **Fuzzy Matching**: Uses Levenshtein distance for flexible equipment name recognition
5. **Real-time UI Feedback**: Modal shows state during recognition, processing, and completion
6. **Fixed UI**: Button always visible, never interferes with page content

## Security Considerations

- ✅ User authenticated before command execution
- ✅ Equipment ownership verified via `belongsToHouse()`
- ✅ House ID validated
- ✅ Command length limited (500 chars max)
- ✅ CSRF not needed (API uses JSON + session/token auth)
- ✅ No sensitive data in modal messages

## Future Enhancements

- [ ] Voice feedback (text-to-speech response)
- [ ] Command history (show previous commands)
- [ ] Custom command macros ("bonne nuit" = turn off multiple lights)
- [ ] Voice confidence scores
- [ ] Support for English/other languages
- [ ] Integration with HomeKit/Alexa for cross-platform compatibility
