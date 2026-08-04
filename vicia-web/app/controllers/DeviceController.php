<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Validator;
use App\Models\ActivityLog;
use App\Models\Device;

/**
 * Class DeviceController
 *
 * Gère l'appairage des cartes ESP32 à la maison actuellement
 * sélectionnée. C'est ce module qui répond à la question « comment
 * sait-on quelle carte physique héberge cet équipement ? » : un
 * technicien saisit ici l'identifiant matériel (chip_id) de la carte
 * lors de son installation, avant de créer les équipements/capteurs
 * qui lui seront rattachés.
 */
class DeviceController extends Controller
{
    public function index(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'resident', 'technician']);
        $devices = Device::forHouse($houseId);
        $this->render('devices/index', ['title' => 'Appareils (ESP32)', 'devices' => $devices]);
    }

    /**
     * Appaire une nouvelle carte ESP32 à la maison actuellement
     * sélectionnée.
     */
    public function store(): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'technician']);
        $this->verifyCsrf();

        $data = [
            'chip_id' => strtoupper(trim((string) $this->request->input('chip_id'))),
            'label'   => trim((string) $this->request->input('label')),
        ];

        $validator = new Validator($data);
        $validator->rules(['chip_id' => 'required|min:4|max:50', 'label' => 'required|min:2|max:100']);
        if ($validator->fails()) {
            Response::error($validator->firstError(), 422, ['errors' => $validator->errors()]);
            return;
        }

        if (Device::findByChipId($data['chip_id'])) {
            Response::error('Cette carte est déjà appairée à une maison (identifiant déjà enregistré).', 409);
            return;
        }

        $id = Device::pair($houseId, $data['chip_id'], $data['label']);
        ActivityLog::record(Auth::id(), 'appairage_carte', "Appairage de la carte « {$data['label']} » ({$data['chip_id']})", $this->request->ip(), $houseId);

        Response::success('Carte appairée avec succès.', ['id' => $id]);
    }

    /**
     * Révoque une carte : elle ne pourra plus être utilisée pour
     * publier/recevoir des commandes tant qu'elle n'est pas
     * ré-appairée. Les équipements/capteurs qui la référencent ne
     * sont pas supprimés (device_id passe à NULL par contrainte FK).
     */
    public function revoke(int $id): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner', 'technician']);
        $this->verifyCsrf();

        if (!Device::belongsToHouse($id, $houseId)) {
            Response::error('Appareil introuvable.', 404);
            return;
        }

        Device::revoke($id);
        ActivityLog::record(Auth::id(), 'revocation_carte', "Révocation d’une carte ESP32", $this->request->ip(), $houseId);

        Response::success('Carte révoquée avec succès.');
    }

    public function destroy(int $id): void
    {
        $houseId = Auth::requireHouseRole(['admin', 'owner']);
        $this->verifyCsrf();

        if (!Device::belongsToHouse($id, $houseId)) {
            Response::error('Appareil introuvable.', 404);
            return;
        }

        Device::delete($id);
        ActivityLog::record(Auth::id(), 'suppression_carte', "Suppression d’une carte ESP32", $this->request->ip(), $houseId);

        Response::success('Carte supprimée avec succès.');
    }
}
