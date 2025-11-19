<?php

/**
 * Plaćena licenca
 * (c) 2025 Tomislav Galić <tomislav@8core.hr>
 * Suradnik: Marko Šimunović <marko@8core.hr>
 * Web: https://8core.hr
 * Kontakt: info@8core.hr | Tel: +385 099 851 0717
 * Sva prava pridržana. Ovaj softver je vlasnički i zabranjeno ga je
 * distribuirati ili mijenjati bez izričitog dopuštenja autora.
 */

require_once __DIR__ . '/akt_helper.class.php';
require_once __DIR__ . '/prilog_helper.class.php';
require_once __DIR__ . '/sortiranje_helper.class.php';
require_once __DIR__ . '/omat_generator.class.php';
require_once __DIR__ . '/predmet_helper.class.php';
require_once DOL_DOCUMENT_ROOT . '/ecm/class/ecmfiles.class.php';

class Predmet_Action_Handler
{
    public static function handleDeleteDocument($db, $conf)
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        ob_start();

        $filename = GETPOST('filename', 'alphanohtml');
        $filepath = GETPOST('filepath', 'alphanohtml');

        if (empty($filename) || empty($filepath)) {
            echo json_encode(['success' => false, 'error' => 'Missing filename or filepath']);
            ob_end_flush();
            exit;
        }

        try {
            $db->begin();

            $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "ecm_files
                    WHERE filename = '" . $db->escape($filename) . "'
                    AND filepath = '" . $db->escape($filepath) . "'
                    AND entity = " . $conf->entity;

            $resql = $db->query($sql);
            if (!$resql || !($ecm_obj = $db->fetch_object($resql))) {
                throw new Exception('ECM file not found in database');
            }

            $ecm_file_id = $ecm_obj->rowid;

            Akt_Helper::deleteAktByEcmFile($db, $ecm_file_id);
            Prilog_Helper::deletePrilogByEcmFile($db, $ecm_file_id);

            $sql = "DELETE FROM " . MAIN_DB_PREFIX . "ecm_files
                    WHERE rowid = " . (int)$ecm_file_id;

            if (!$db->query($sql)) {
                throw new Exception('Failed to delete ECM record: ' . $db->lasterror());
            }

            $full_path = DOL_DATA_ROOT . '/ecm/' . $filepath . '/' . $filename;
            if (file_exists($full_path)) {
                if (!unlink($full_path)) {
                    dol_syslog("Warning: Could not delete physical file: " . $full_path, LOG_WARNING);
                }
            }

            $db->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Dokument je uspješno obrisan'
            ]);

        } catch (Exception $e) {
            $db->rollback();
            dol_syslog("Error deleting document: " . $e->getMessage(), LOG_ERR);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        ob_end_flush();
        exit;
    }

    public static function handleUploadAkt($db, $conf, $user, $caseId)
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        ob_start();

        try {
            Akt_Helper::createAktiTable($db);

            if (!isset($_FILES['akt_file']) || !is_uploaded_file($_FILES['akt_file']['tmp_name'])) {
                throw new Exception("Datoteka nije uploadana");
            }

            $file = $_FILES['akt_file'];
            $allowed_mimes = [
                'application/pdf' => 'pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                'application/msword' => 'doc',
                'application/vnd.ms-excel' => 'xls',
                'image/jpeg' => 'jpg',
                'image/png' => 'png'
            ];

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $mime = explode(';', $mime)[0];
            $mime = trim($mime);

            if (!isset($allowed_mimes[$mime])) {
                throw new Exception("Nevaljan tip datoteke: " . $mime);
            }

            $relative_path = Predmet_helper::getPredmetFolderPath($caseId, $db);
            $predmet_dir = DOL_DATA_ROOT . '/ecm/' . $relative_path;

            if (!is_dir($predmet_dir)) {
                dol_mkdir($predmet_dir);
            }

            $filename = preg_replace('/[^a-zA-Z0-9-_\.]/', '', basename($file['name']));
            $filename = substr($filename, 0, 255);

            $fullpath = $predmet_dir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $fullpath)) {
                throw new Exception("Greška pri premještanju datoteke");
            }

            $ecmfile = new EcmFiles($db);
            $ecm_filepath = rtrim($relative_path, '/');
            $ecmfile->filepath = $ecm_filepath;
            $ecmfile->filename = $filename;
            $ecmfile->label = $filename;
            $ecmfile->entity = $conf->entity;
            $ecmfile->gen_or_uploaded = 'uploaded';
            $ecmfile->description = 'Akt za predmet ' . $caseId;
            $ecmfile->fk_user_c = $user->id;
            $ecmfile->fk_user_m = $user->id;
            $ecmfile->date_c = dol_now();
            $ecmfile->date_m = dol_now();

            $ecm_result = $ecmfile->create($user);
            if ($ecm_result < 0) {
                $error_msg = "ECM creation failed (AKT)";
                if (!empty($ecmfile->error)) {
                    $error_msg .= ": " . $ecmfile->error;
                }
                if (!empty($ecmfile->errors)) {
                    $error_msg .= " | Errors: " . implode(', ', $ecmfile->errors);
                }
                $error_msg .= " | DB Error: " . $db->lasterror();
                dol_syslog("AKT ECM ERROR: " . $error_msg, LOG_ERR);
                throw new Exception($error_msg);
            }

            $akt_result = Akt_Helper::createAkt($db, $caseId, $ecm_result, $user->id);
            if (!$akt_result['success']) {
                throw new Exception("Akt creation failed: " . $akt_result['error']);
            }

            echo json_encode([
                'success' => true,
                'message' => 'Akt uspješno dodan s brojem: ' . $akt_result['urb_broj'],
                'urb_broj' => $akt_result['urb_broj'],
                'filename' => $filename
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        ob_end_flush();
        exit;
    }

    public static function handleUploadPrilog($db, $conf, $user, $caseId)
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        ob_start();

        try {
            Prilog_Helper::createPriloziTable($db);

            $akt_id = GETPOST('akt_id', 'int');
            if (!$akt_id) {
                throw new Exception("Morate odabrati akt");
            }

            if (!isset($_FILES['prilog_file']) || !is_uploaded_file($_FILES['prilog_file']['tmp_name'])) {
                throw new Exception("Datoteka nije uploadana");
            }

            $file = $_FILES['prilog_file'];
            $allowed_mimes = [
                'application/pdf' => 'pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                'application/msword' => 'doc',
                'application/vnd.ms-excel' => 'xls',
                'image/jpeg' => 'jpg',
                'image/png' => 'png'
            ];

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $mime = explode(';', $mime)[0];
            $mime = trim($mime);

            if (!isset($allowed_mimes[$mime])) {
                throw new Exception("Nevaljan tip datoteke: " . $mime);
            }

            $relative_path = Predmet_helper::getPredmetFolderPath($caseId, $db);
            $predmet_dir = DOL_DATA_ROOT . '/ecm/' . $relative_path;

            if (!is_dir($predmet_dir)) {
                dol_mkdir($predmet_dir);
            }

            $filename = preg_replace('/[^a-zA-Z0-9-_\.]/', '', basename($file['name']));
            $filename = substr($filename, 0, 255);

            $fullpath = $predmet_dir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $fullpath)) {
                throw new Exception("Greška pri premještanju datoteke");
            }

            $ecmfile = new EcmFiles($db);
            $ecm_filepath = rtrim($relative_path, '/');
            $ecmfile->filepath = $ecm_filepath;
            $ecmfile->filename = $filename;
            $ecmfile->label = $filename;
            $ecmfile->entity = $conf->entity;
            $ecmfile->gen_or_uploaded = 'uploaded';
            $ecmfile->description = 'Prilog za predmet ' . $caseId;
            $ecmfile->fk_user_c = $user->id;
            $ecmfile->fk_user_m = $user->id;
            $ecmfile->date_c = dol_now();
            $ecmfile->date_m = dol_now();

            $ecm_result = $ecmfile->create($user);
            if ($ecm_result < 0) {
                $error_msg = "ECM creation failed (PRILOG)";
                if (!empty($ecmfile->error)) {
                    $error_msg .= ": " . $ecmfile->error;
                }
                if (!empty($ecmfile->errors)) {
                    $error_msg .= " | Errors: " . implode(', ', $ecmfile->errors);
                }
                $error_msg .= " | DB Error: " . $db->lasterror();
                dol_syslog("PRILOG ECM ERROR: " . $error_msg, LOG_ERR);
                throw new Exception($error_msg);
            }

            $prilog_result = Prilog_Helper::createPrilog($db, $akt_id, $caseId, $ecm_result, $user->id);
            if (!$prilog_result['success']) {
                throw new Exception("Prilog creation failed: " . $prilog_result['error']);
            }

            $sql = "SELECT urb_broj FROM " . MAIN_DB_PREFIX . "a_akti WHERE ID_akta = " . (int)$akt_id;
            $resql = $db->query($sql);
            $akt_urb = '00';
            if ($resql && $obj = $db->fetch_object($resql)) {
                $akt_urb = $obj->urb_broj;
            }

            echo json_encode([
                'success' => true,
                'message' => 'Prilog uspješno dodan: Akt ' . $akt_urb . ' - Prilog ' . $prilog_result['prilog_rbr'],
                'akt_urb' => $akt_urb,
                'prilog_rbr' => $prilog_result['prilog_rbr'],
                'filename' => $filename
            ]);

        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }

        ob_end_flush();
        exit;
    }

    public static function handleGenerateOmot($db, $conf, $user, $langs, $caseId)
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        ob_start();

        $omot_generator = new Omat_Generator($db, $conf, $user, $langs);
        $result = $omot_generator->generateOmat($caseId, true);

        echo json_encode($result);

        ob_end_flush();
        exit;
    }

    public static function handlePreviewOmot($db, $conf, $user, $langs, $caseId)
    {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        ob_start();

        $omot_generator = new Omat_Generator($db, $conf, $user, $langs);
        $result = $omot_generator->generatePreview($caseId);

        echo json_encode($result);

        ob_end_flush();
        exit;
    }

    public static function handleGetNedodjeljeni($db, $conf)
    {
        @ob_end_clean();
        header('Content-Type: application/json');
        ob_start();

        $predmet_id = GETPOST('predmet_id', 'int');
        if (!$predmet_id) {
            echo json_encode(['success' => false, 'error' => 'Missing predmet ID']);
            ob_end_flush();
            exit;
        }

        $result = Sortiranje_Helper::getNedodjeljeneDokumente($db, $conf, $predmet_id);
        $available_akti = Sortiranje_Helper::getAvailableAktiForAssignment($db, $predmet_id);
        $result['available_akti'] = $available_akti;

        echo json_encode($result);

        ob_end_flush();
        exit;
    }

    public static function handleBulkAssignDocuments($db, $conf, $user)
    {
        @ob_end_clean();
        header('Content-Type: application/json');
        ob_start();

        $predmet_id = GETPOST('predmet_id', 'int');
        $assignments_json = GETPOST('assignments', 'none');
        if ($assignments_json === '' && isset($_POST['assignments'])) {
            $assignments_json = $_POST['assignments'];
        }

        if (!$predmet_id || !$assignments_json) {
            echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
            ob_end_flush();
            exit;
        }

        $assignments = json_decode($assignments_json, true);
        if (!is_array($assignments)) {
            echo json_encode(['success' => false, 'error' => 'Invalid assignments data']);
            ob_end_flush();
            exit;
        }

        foreach ($assignments as $assignment) {
            $validation = Sortiranje_Helper::validateAssignment($assignment);
            if (!$validation['valid']) {
                echo json_encode(['success' => false, 'error' => $validation['error']]);
                ob_end_flush();
                exit;
            }
        }

        $result = Sortiranje_Helper::bulkAssign($db, $conf, $user, $predmet_id, $assignments);
        echo json_encode($result);

        ob_end_flush();
        exit;
    }
}
