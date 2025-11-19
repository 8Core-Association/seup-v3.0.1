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
/**
 *	\file       seup/predmet.php
 *	\ingroup    seup
 *	\brief      Individual predmet view with documents and details
 */

// Disable CSRF token validation globally for this page
define('NOCSRFCHECK', 1);

// Check if this is an AJAX upload request - handle it BEFORE loading Dolibarr
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $ajax_action = $_POST['action'];
    if (in_array($ajax_action, ['upload_akt', 'upload_prilog', 'delete_document', 'generate_omot', 'preview_omot'])) {
        // This is an AJAX request - we need to handle it differently
        define('AJAX_REQUEST', 1);
        define('NOTOKENRENEWAL', 1);
    }
}

// Učitaj Dolibarr okruženje
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
    $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
    $i--;
    $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
    $res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
    $res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
    $res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    die("Include of main fails");
}

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/ecm/class/ecmfiles.class.php';

// Local classes
require_once __DIR__ . '/../class/predmet_helper.class.php';
require_once __DIR__ . '/../class/request_handler.class.php';
require_once __DIR__ . '/../class/omat_generator.class.php';
require_once __DIR__ . '/../class/akt_helper.class.php';
require_once __DIR__ . '/../class/prilog_helper.class.php';
require_once __DIR__ . '/../class/sortiranje_helper.class.php';

// Load translation files
$langs->loadLangs(array("seup@seup"));

// Get predmet ID
$caseId = GETPOST('id', 'int');
if (!$caseId) {
    header('Location: predmeti.php');
    exit;
}

// Security check
$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
    $socid = $user->socid;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    define('NOTOKENRENEWAL', 1);

    // Get action from POST only (ignore GET params)
    $action = isset($_POST['action']) ? $_POST['action'] : GETPOST('action', 'alpha');

    if ($action === 'delete_document') {
        // Clear ALL output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        ob_start();
        
        $filename = GETPOST('filename', 'alphanohtml');
        $filepath = GETPOST('filepath', 'alphanohtml');
        
        if (empty($filename) || empty($filepath)) {
            echo json_encode(['success' => false, 'error' => 'Missing filename or filepath']);
            exit;
        }
        
        try {
            $db->begin();
            
            // Get ECM file record
            $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "ecm_files 
                    WHERE filename = '" . $db->escape($filename) . "'
                    AND filepath = '" . $db->escape($filepath) . "'
                    AND entity = " . $conf->entity;
            
            $resql = $db->query($sql);
            if (!$resql || !($ecm_obj = $db->fetch_object($resql))) {
                throw new Exception('ECM file not found in database');
            }
            
            $ecm_file_id = $ecm_obj->rowid;
            
            // Delete akt record (this will cascade delete prilozi)
            require_once __DIR__ . '/../class/akt_helper.class.php';
            Akt_Helper::deleteAktByEcmFile($db, $ecm_file_id);
            
            // Delete prilog record (in case it's a standalone prilog)
            require_once __DIR__ . '/../class/prilog_helper.class.php';
            Prilog_Helper::deletePrilogByEcmFile($db, $ecm_file_id);
            
            // Delete ECM record
            $sql = "DELETE FROM " . MAIN_DB_PREFIX . "ecm_files 
                    WHERE rowid = " . (int)$ecm_file_id;
            
            if (!$db->query($sql)) {
                throw new Exception('Failed to delete ECM record: ' . $db->lasterror());
            }
            
            // Delete physical file
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
    
    // Handle document upload
    if ($action === 'upload_document') {
        Request_Handler::handleUploadDocument($db, '', $langs, $conf, $user);
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $caseId);
        exit;
    }
    
    // Handle akt upload
    if ($action === 'upload_akt') {
        // Clear ALL output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        ob_start();
        
        try {
            require_once __DIR__ . '/../class/akt_helper.class.php';
            
            // Ensure a_akti table exists
            Akt_Helper::createAktiTable($db);
            
            // Handle file upload first
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
            
            // Get predmet folder path
            $relative_path = Predmet_helper::getPredmetFolderPath($caseId, $db);
            $predmet_dir = DOL_DATA_ROOT . '/ecm/' . $relative_path;
            
            if (!is_dir($predmet_dir)) {
                dol_mkdir($predmet_dir);
            }
            
            // Sanitize filename
            $filename = preg_replace('/[^a-zA-Z0-9-_\.]/', '', basename($file['name']));
            $filename = substr($filename, 0, 255);
            
            // Move uploaded file
            $fullpath = $predmet_dir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $fullpath)) {
                throw new Exception("Greška pri premještanju datoteke");
            }
            
            // Create ECM record
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
            
            // Create akt record
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

    // Handle prilog upload
    if ($action === 'upload_prilog') {
        // Clear ALL output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        ob_start();
        
        try {
            require_once __DIR__ . '/../class/prilog_helper.class.php';
            
            // Ensure a_prilozi table exists
            Prilog_Helper::createPriloziTable($db);
            
            $akt_id = GETPOST('akt_id', 'int');
            if (!$akt_id) {
                throw new Exception("Morate odabrati akt");
            }
            
            // Handle file upload
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
            
            // Get predmet folder path
            $relative_path = Predmet_helper::getPredmetFolderPath($caseId, $db);
            $predmet_dir = DOL_DATA_ROOT . '/ecm/' . $relative_path;
            
            if (!is_dir($predmet_dir)) {
                dol_mkdir($predmet_dir);
            }
            
            // Sanitize filename
            $filename = preg_replace('/[^a-zA-Z0-9-_\.]/', '', basename($file['name']));
            $filename = substr($filename, 0, 255);
            
            // Move uploaded file
            $fullpath = $predmet_dir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $fullpath)) {
                throw new Exception("Greška pri premještanju datoteke");
            }
            
            // Create ECM record
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
            
            // Create prilog record
            $prilog_result = Prilog_Helper::createPrilog($db, $akt_id, $caseId, $ecm_result, $user->id);
            if (!$prilog_result['success']) {
                throw new Exception("Prilog creation failed: " . $prilog_result['error']);
            }

            // Get akt urb broj for response
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

    // Handle document deletion (duplicate - second occurrence)
    if ($action === 'delete_document') {
        @ob_end_clean();
        header('Content-Type: application/json');
        ob_start();
        
        $filename = GETPOST('filename', 'alpha');
        $filepath = GETPOST('filepath', 'alpha');
        
        if (empty($filename) || empty($filepath)) {
            echo json_encode(['success' => false, 'error' => 'Missing filename or filepath']);
            exit;
        }
        
        try {
            // Delete from filesystem
            $full_path = DOL_DATA_ROOT . '/ecm/' . rtrim($filepath, '/') . '/' . $filename;
            if (file_exists($full_path)) {
                unlink($full_path);
            }
            
            // Delete from ECM database
            $sql = "DELETE FROM " . MAIN_DB_PREFIX . "ecm_files 
                    WHERE filepath = '" . $db->escape(rtrim($filepath, '/')) . "'
                    AND filename = '" . $db->escape($filename) . "'
                    AND entity = " . $conf->entity;
            
            if ($db->query($sql)) {
                echo json_encode(['success' => true, 'message' => 'Dokument je uspješno obrisan']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $db->lasterror()]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }

        ob_end_flush();
        exit;
    }

    // Handle omat generation
    if ($action === 'generate_omot') {
        // Clear ALL output buffers
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

    // Handle omot preview
    if ($action === 'preview_omot') {
        // Clear ALL output buffers
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

    // Handle get nedodjeljeni documents
    if ($action === 'get_nedodjeljeni') {
        @ob_end_clean();
        header('Content-Type: application/json');
        ob_start();
        
        $predmet_id = GETPOST('predmet_id', 'int');
        if (!$predmet_id) {
            echo json_encode(['success' => false, 'error' => 'Missing predmet ID']);
            exit;
        }
        
        $result = Sortiranje_Helper::getNedodjeljeneDokumente($db, $conf, $predmet_id);

        // Also get available akti for assignment
        $available_akti = Sortiranje_Helper::getAvailableAktiForAssignment($db, $predmet_id);
        $result['available_akti'] = $available_akti;

        echo json_encode($result);

        ob_end_flush();
        exit;
    }

    // Handle bulk assign documents
    if ($action === 'bulk_assign_documents') {
        @ob_end_clean();
        header('Content-Type: application/json');
        ob_start();
        
        $predmet_id = GETPOST('predmet_id', 'int');
        $assignments_json = GETPOST('assignments', 'none');
        if ($assignments_json === '' && isset($_POST['assignments'])) { $assignments_json = $_POST['assignments']; }
        
        if (!$predmet_id || !$assignments_json) {
            echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
            exit;
        }
        
        $assignments = json_decode($assignments_json, true);
        if (!is_array($assignments)) {
            echo json_encode(['success' => false, 'error' => 'Invalid assignments data']);
            exit;
        }
        
        // Validate each assignment
        foreach ($assignments as $assignment) {
            $validation = Sortiranje_Helper::validateAssignment($assignment);
            if (!$validation['valid']) {
                echo json_encode(['success' => false, 'error' => $validation['error']]);
                exit;
            }
        }

        $result = Sortiranje_Helper::bulkAssign($db, $conf, $user, $predmet_id, $assignments);
        echo json_encode($result);

        ob_end_flush();
        exit;
    }
}

// Fetch predmet details
$sql = "SELECT 
            p.ID_predmeta,
            p.klasa_br,
            p.sadrzaj,
            p.dosje_broj,
            p.godina,
            p.predmet_rbr,
            p.naziv_predmeta,
            DATE_FORMAT(p.tstamp_created, '%d.%m.%Y') as datum_otvaranja,
            u.name_ustanova,
            u.code_ustanova,
            k.ime_prezime,
            k.rbr as korisnik_rbr,
            k.naziv as radno_mjesto,
            ko.opis_klasifikacijske_oznake,
            ko.vrijeme_cuvanja
        FROM " . MAIN_DB_PREFIX . "a_predmet p
        LEFT JOIN " . MAIN_DB_PREFIX . "a_oznaka_ustanove u ON p.ID_ustanove = u.ID_ustanove
        LEFT JOIN " . MAIN_DB_PREFIX . "a_interna_oznaka_korisnika k ON p.ID_interna_oznaka_korisnika = k.ID
        LEFT JOIN " . MAIN_DB_PREFIX . "a_klasifikacijska_oznaka ko ON p.ID_klasifikacijske_oznake = ko.ID_klasifikacijske_oznake
        WHERE p.ID_predmeta = " . (int)$caseId;

$resql = $db->query($sql);
$predmet = null;
if ($resql && $obj = $db->fetch_object($resql)) {
    $predmet = $obj;
    $predmet->klasa_format = $obj->klasa_br . '-' . $obj->sadrzaj . '/' . 
                            $obj->godina . '-' . $obj->dosje_broj . '/' . 
                            $obj->predmet_rbr;
}

if (!$predmet) {
    header('Location: predmeti.php');
    exit;
}

// Fetch uploaded documents
$documentTableHTML = '';
Predmet_helper::fetchUploadedDocuments($db, $conf, $documentTableHTML, $langs, $caseId);

// Get available akti for prilog dropdown
$availableAkti = Prilog_Helper::getAvailableAkti($db, $caseId);

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader("", "Predmet: " . $predmet->klasa_format, '', '', 0, 0, '', '', '', 'mod-seup page-predmet');

// Modern design assets
print '<meta name="viewport" content="width=device-width, initial-scale=1">';
print '<link rel="preconnect" href="https://fonts.googleapis.com">';
print '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
print '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">';
print '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
print '<link href="/custom/seup/css/seup-modern.css" rel="stylesheet">';
print '<link href="/custom/seup/css/predmet.css" rel="stylesheet">';
print '<link href="/custom/seup/css/prilozi.css" rel="stylesheet">';
print '<link href="/custom/seup/css/dopina_predmet.css" rel="stylesheet">';
print '<link href="/custom/seup/css/predmet-sortiranje.css" rel="stylesheet">';

// Main container
print '<div class="seup-predmet-container">';

// Case details header
print '<div class="seup-case-details">';
print '<div class="seup-case-header">';
print '<div class="seup-case-icon"><i class="fas fa-folder-open"></i></div>';
print '<div class="seup-case-title">';
print '<h4>' . htmlspecialchars($predmet->naziv_predmeta) . '</h4>';
print '<div class="seup-case-klasa">' . $predmet->klasa_format . '</div>';
print '</div>';
print '</div>';

print '<div class="seup-case-grid">';

print '<div class="seup-case-field">';
print '<div class="seup-case-field-label"><i class="fas fa-building"></i>Ustanova</div>';
print '<div class="seup-case-field-value">' . ($predmet->name_ustanova ?: 'N/A') . '</div>';
print '</div>';

print '<div class="seup-case-field">';
print '<div class="seup-case-field-label"><i class="fas fa-user"></i>Zaposlenik</div>';
print '<div class="seup-case-field-value">' . ($predmet->ime_prezime ?: 'N/A') . '</div>';
print '</div>';

print '<div class="seup-case-field">';
print '<div class="seup-case-field-label"><i class="fas fa-calendar"></i>Datum Otvaranja</div>';
print '<div class="seup-case-field-value">' . $predmet->datum_otvaranja . '</div>';
print '</div>';

print '<div class="seup-case-field">';
print '<div class="seup-case-field-label"><i class="fas fa-clock"></i>Vrijeme Čuvanja</div>';
$vrijeme_text = ($predmet->vrijeme_cuvanja == 0) ? 'Trajno' : $predmet->vrijeme_cuvanja . ' godina';
print '<div class="seup-case-field-value">' . $vrijeme_text . '</div>';
print '</div>';

print '</div>'; // seup-case-grid
print '</div>'; // seup-case-details

// Tab navigation
print '<div class="seup-tabs">';
print '<button class="seup-tab active" data-tab="prilozi"><i class="fas fa-paperclip"></i>Prilozi</button>';
print '<button class="seup-tab" data-tab="prepregled"><i class="fas fa-eye"></i>Prepregled</button>';
print '<button class="seup-tab" data-tab="statistike"><i class="fas fa-chart-bar"></i>Statistike</button>';
print '</div>';

// Tab content
print '<div class="seup-tab-content">';

// Tab 1: Prilozi (Documents)
print '<div class="seup-tab-pane active" id="prilozi">';

// Upload section
print '<div class="seup-upload-section">';
print '<i class="fas fa-cloud-upload-alt seup-upload-icon"></i>';
print '<p class="seup-upload-text">Dodajte novi dokument u predmet</p>';
print '<div class="seup-upload-buttons">';
print '<button type="button" class="seup-btn seup-btn-primary" id="dodajAktBtn">';
print '<i class="fas fa-file-alt me-2"></i>Dodaj Akt';
print '</button>';
print '<button type="button" class="seup-btn seup-btn-secondary" id="dodajPrilogBtn">';
print '<i class="fas fa-paperclip me-2"></i>Dodaj Prilog';
print '</button>';
print '<button type="button" class="seup-btn seup-btn-warning" id="sortirajNedodjeljenoBtn">';
print '<i class="fas fa-sort me-2"></i>Sortiraj nedodjeljeno';
print '</button>';
print '</div>';
print '<div class="seup-upload-progress" id="uploadProgress">';
print '<div class="seup-progress-bar"><div class="seup-progress-fill" id="progressFill"></div></div>';
print '<div class="seup-progress-text" id="progressText">Učitavanje...</div>';
print '</div>';
print '</div>';

// Documents list
print '<div class="seup-documents-header">';
print '<h5 class="seup-documents-title"><i class="fas fa-paperclip"></i>Akti i Prilozi Predmeta</h5>';
print '</div>';

print $documentTableHTML;

print '</div>'; // Tab 1

// Tab 2: Prepregled
print '<div class="seup-tab-pane" id="prepregled">';
print '<div class="seup-preview-container">';
print '<i class="fas fa-file-alt seup-preview-icon"></i>';
print '<h4 class="seup-preview-title">Omot Spisa</h4>';
print '<p class="seup-preview-description">Generirajte ili pregledajte A3 omat spisa s osnovnim informacijama i popisom privitaka</p>';

print '<div class="seup-action-buttons">';
print '<button type="button" class="seup-btn seup-btn-primary" id="generateOmotBtn">';
print '<i class="fas fa-file-pdf me-2"></i>Kreiraj PDF';
print '</button>';
print '<button type="button" class="seup-btn seup-btn-secondary" id="printOmotBtn">';
print '<i class="fas fa-print me-2"></i>Ispis';
print '</button>';
print '<button type="button" class="seup-btn seup-btn-success" id="previewOmotBtn">';
print '<i class="fas fa-eye me-2"></i>Prepregled';
print '</button>';
print '</div>';

print '</div>';
print '</div>'; // Tab 2

// Tab 3: Statistike
print '<div class="seup-tab-pane" id="statistike">';
print '<div class="seup-stats-container">';
print '<h5><i class="fas fa-chart-bar"></i>Statistike Predmeta</h5>';
print '<div class="seup-stats-grid">';

// Count documents
$doc_count = 0;
$relative_path = Predmet_helper::getPredmetFolderPath($caseId, $db);
$ecm_filepath = rtrim($relative_path, '/');
$sql = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "ecm_files
        WHERE filepath = '" . $db->escape($ecm_filepath) . "'
        AND entity = " . $conf->entity;
$resql = $db->query($sql);
if ($resql && $obj = $db->fetch_object($resql)) {
    $doc_count = $obj->count;
}

print '<div class="seup-stat-card">';
print '<i class="fas fa-file-alt seup-stat-icon"></i>';
print '<div class="seup-stat-number">' . $doc_count . '</div>';
print '<div class="seup-stat-label">Dokumenata</div>';
print '</div>';

print '<div class="seup-stat-card">';
print '<i class="fas fa-calendar seup-stat-icon"></i>';
print '<div class="seup-stat-number">' . $predmet->datum_otvaranja . '</div>';
print '<div class="seup-stat-label">Datum Otvaranja</div>';
print '</div>';

print '<div class="seup-stat-card">';
print '<i class="fas fa-clock seup-stat-icon"></i>';
print '<div class="seup-stat-number">' . $vrijeme_text . '</div>';
print '<div class="seup-stat-label">Vrijeme Čuvanja</div>';
print '</div>';

print '<div class="seup-stat-card">';
print '<i class="fas fa-user seup-stat-icon"></i>';
print '<div class="seup-stat-number">' . ($predmet->korisnik_rbr ?: 'N/A') . '</div>';
print '<div class="seup-stat-label">Oznaka Korisnika</div>';
print '</div>';

print '</div>'; // seup-stats-grid
print '</div>'; // seup-stats-container
print '</div>'; // Tab 3

print '</div>'; // seup-tab-content
print '</div>'; // seup-predmet-container

// Omot Preview Modal
print '<div class="seup-modal" id="omotPreviewModal">';
print '<div class="seup-modal-content" style="max-width: 800px; max-height: 90vh;">';
print '<div class="seup-modal-header">';
print '<h5 class="seup-modal-title"><i class="fas fa-eye me-2"></i>Prepregled Omota Spisa</h5>';
print '<button type="button" class="seup-modal-close" id="closeOmotModal">&times;</button>';
print '</div>';
print '<div class="seup-modal-body" style="max-height: 70vh; overflow-y: auto;">';
print '<div id="omotPreviewContent">';
print '<div class="seup-loading-message"><i class="fas fa-spinner fa-spin"></i> Učitavam prepregled...</div>';
print '</div>';
print '</div>';
print '<div class="seup-modal-footer">';
print '<button type="button" class="seup-btn seup-btn-secondary" id="closePreviewBtn">Zatvori</button>';
print '<button type="button" class="seup-btn seup-btn-primary" id="generateFromPreviewBtn">';
print '<i class="fas fa-file-pdf me-2"></i>Generiraj PDF';
print '</button>';
print '</div>';
print '</div>';
print '</div>';

// Print Instructions Modal
print '<div class="seup-modal" id="printInstructionsModal">';
print '<div class="seup-modal-content" style="max-width: 600px;">';
print '<div class="seup-modal-header">';
print '<h5 class="seup-modal-title"><i class="fas fa-print me-2"></i>Upute za Ispis Omota Spisa</h5>';
print '<button type="button" class="seup-modal-close" id="closePrintModal">&times;</button>';
print '</div>';
print '<div class="seup-modal-body">';
print '<div class="seup-print-instructions">';
print '<div class="seup-print-warning">';
print '<div class="seup-warning-icon"><i class="fas fa-exclamation-triangle"></i></div>';
print '<div class="seup-warning-content">';
print '<h4>Važne upute za ispis</h4>';
print '<p>Molimo pažljivo pročitajte upute prije ispisa omota spisa</p>';
print '</div>';
print '</div>';

print '<div class="seup-print-steps">';
print '<div class="seup-print-step">';
print '<div class="seup-step-number">1</div>';
print '<div class="seup-step-content">';
print '<h5>Postavke printera</h5>';
print '<p>Postavite printer na <strong>A3 format papira</strong> (297 x 420 mm)</p>';
print '</div>';
print '</div>';

print '<div class="seup-print-step">';
print '<div class="seup-step-number">2</div>';
print '<div class="seup-step-content">';
print '<h5>Orijentacija</h5>';
print '<p>Odaberite <strong>Portrait</strong> (uspravnu) orijentaciju</p>';
print '</div>';
print '</div>';

print '<div class="seup-print-step">';
print '<div class="seup-step-number">3</div>';
print '<div class="seup-step-content">';
print '<h5>Margine</h5>';
print '<p>Postavite margine na <strong>minimum</strong> ili koristite "Fit to page"</p>';
print '</div>';
print '</div>';

print '<div class="seup-print-step">';
print '<div class="seup-step-number">4</div>';
print '<div class="seup-step-content">';
print '<h5>Preklapanje</h5>';
print '<p>Nakon ispisa, <strong>preklopite papir na pola</strong> da formirate omot</p>';
print '</div>';
print '</div>';
print '</div>';

print '<div class="seup-print-note">';
print '<div class="seup-note-icon"><i class="fas fa-lightbulb"></i></div>';
print '<div class="seup-note-content">';
print '<h5>Napomena</h5>';
print '<p>Omot spisa je dizajniran za A3 papir koji se preklapa na pola. ';
print 'Stranica 1 je naslovnica, stranice 2-3 su unutarnje (popis privitaka), a stranica 4 je zadnja.</p>';
print '</div>';
print '</div>';

print '</div>'; // seup-print-instructions
print '</div>';
print '<div class="seup-modal-footer">';
print '<button type="button" class="seup-btn seup-btn-secondary" id="cancelPrintBtn">Odustani</button>';
print '<button type="button" class="seup-btn seup-btn-primary" id="confirmPrintBtn">';
print '<i class="fas fa-print me-2"></i>Ispiši Omot';
print '</button>';
print '</div>';
print '</div>';
print '</div>';

// Delete Document Modal
print '<div class="seup-modal" id="deleteDocModal">';
print '<div class="seup-modal-content">';
print '<div class="seup-modal-header">';
print '<h5 class="seup-modal-title"><i class="fas fa-trash me-2"></i>Brisanje Dokumenta</h5>';
print '<button type="button" class="seup-modal-close" id="closeDeleteModal">&times;</button>';
print '</div>';
print '<div class="seup-modal-body">';
print '<div class="seup-delete-doc-info">';
print '<div class="seup-delete-doc-icon"><i class="fas fa-file-alt"></i></div>';
print '<div class="seup-delete-doc-details">';
print '<div class="seup-delete-doc-name" id="deleteDocName">document.pdf</div>';
print '<div class="seup-delete-doc-warning">';
print '<i class="fas fa-exclamation-triangle"></i>';
print 'Jeste li sigurni da želite obrisati ovaj dokument? Ova akcija je nepovratna.';
print '</div>';
print '</div>';
print '</div>';
print '</div>';
print '<div class="seup-modal-footer">';
print '<button type="button" class="seup-btn seup-btn-secondary" id="cancelDeleteBtn">Odustani</button>';
print '<button type="button" class="seup-btn seup-btn-danger" id="confirmDeleteBtn">';
print '<i class="fas fa-trash me-2"></i>Obriši';
print '</button>';
print '</div>';
print '</div>';
print '</div>';

// Akt Upload Modal
print '<div class="seup-modal" id="aktUploadModal">';
print '<div class="seup-modal-content">';
print '<div class="seup-modal-header">';
print '<h5 class="seup-modal-title"><i class="fas fa-file-alt me-2"></i>Dodaj Akt</h5>';
print '<button type="button" class="seup-modal-close" id="closeAktModal">&times;</button>';
print '</div>';
print '<div class="seup-modal-body">';
print '<div class="seup-akt-upload-info">';
print '<div class="seup-akt-info-icon"><i class="fas fa-info-circle"></i></div>';
print '<div class="seup-akt-info-content">';
print '<h6>Dodavanje novog akta</h6>';
print '<p>Akt će automatski dobiti sljedeći urb broj u nizu</p>';
print '</div>';
print '</div>';
print '<form id="aktUploadForm" enctype="multipart/form-data">';
print '<input type="hidden" name="action" value="upload_akt">';
print '<input type="hidden" name="case_id" value="' . $caseId . '">';
print '<div class="seup-form-group">';
print '<label for="aktFile" class="seup-label"><i class="fas fa-file me-2"></i>Odaberite datoteku</label>';
print '<input type="file" id="aktFile" name="akt_file" class="seup-input" accept=".pdf,.docx,.xlsx,.doc,.xls,.jpg,.jpeg,.png" required>';
print '<div class="seup-help-text">Podržani formati: PDF, DOCX, XLSX, DOC, XLS, JPG, PNG</div>';
print '</div>';
print '</form>';
print '</div>';
print '<div class="seup-modal-footer">';
print '<button type="button" class="seup-btn seup-btn-secondary" id="cancelAktBtn">Odustani</button>';
print '<button type="button" class="seup-btn seup-btn-primary" id="uploadAktBtn">';
print '<i class="fas fa-upload me-2"></i>Upload Akt';
print '</button>';
print '</div>';
print '</div>';
print '</div>';

// Prilog Upload Modal
print '<div class="seup-modal" id="prilogUploadModal">';
print '<div class="seup-modal-content">';
print '<div class="seup-modal-header">';
print '<h5 class="seup-modal-title"><i class="fas fa-paperclip me-2"></i>Dodaj Prilog</h5>';
print '<button type="button" class="seup-modal-close" id="closePrilogModal">&times;</button>';
print '</div>';
print '<div class="seup-modal-body">';
print '<div class="seup-prilog-upload-info">';
print '<div class="seup-prilog-info-icon"><i class="fas fa-info-circle"></i></div>';
print '<div class="seup-prilog-info-content">';
print '<h6>Dodavanje novog priloga</h6>';
print '<p>Prilog će biti dodan pod odabrani akt s automatskim brojem</p>';
print '</div>';
print '</div>';

if (count($availableAkti) > 0) {
    print '<form id="prilogUploadForm" enctype="multipart/form-data">';
    print '<input type="hidden" name="action" value="upload_prilog">';
    print '<input type="hidden" name="case_id" value="' . $caseId . '">';
    
    print '<div class="seup-form-group">';
    print '<label for="aktSelect" class="seup-label"><i class="fas fa-file-alt me-2"></i>Odaberite akt</label>';
    print '<select id="aktSelect" name="akt_id" class="seup-select" required>';
    print '<option value="">-- Odaberite akt --</option>';
    foreach ($availableAkti as $akt) {
        print '<option value="' . $akt->ID_akta . '">';
        print 'Akt ' . $akt->urb_broj . ' - ' . htmlspecialchars($akt->filename);
        print '</option>';
    }
    print '</select>';
    print '</div>';
    
    print '<div class="seup-form-group">';
    print '<label for="prilogFile" class="seup-label"><i class="fas fa-file me-2"></i>Odaberite datoteku</label>';
    print '<input type="file" id="prilogFile" name="prilog_file" class="seup-input" accept=".pdf,.docx,.xlsx,.doc,.xls,.jpg,.jpeg,.png" required>';
    print '<div class="seup-help-text">Podržani formati: PDF, DOCX, XLSX, DOC, XLS, JPG, PNG</div>';
    print '</div>';
    print '</form>';
} else {
    print '<div class="seup-alert seup-alert-warning">';
    print '<i class="fas fa-exclamation-triangle me-2"></i>';
    print 'Nema dostupnih akata. Prvo dodajte akt prije dodavanja priloga.';
    print '</div>';
}

print '</div>';
print '<div class="seup-modal-footer">';
if (count($availableAkti) > 0) {
    print '<button type="button" class="seup-btn seup-btn-secondary" id="cancelPrilogBtn">Odustani</button>';
    print '<button type="button" class="seup-btn seup-btn-success" id="uploadPrilogBtn">';
    print '<i class="fas fa-upload me-2"></i>Upload Prilog';
    print '</button>';
} else {
    print '<button type="button" class="seup-btn seup-btn-primary" id="dodajAktPrviBtn">';
    print '<i class="fas fa-file-alt me-2"></i>Dodaj Prvi Akt';
    print '</button>';
}
print '</div>';
print '</div>';
print '</div>';

// JavaScript for enhanced functionality
print '<script src="/custom/seup/js/seup-modern.js"></script>';
print '<script src="/custom/seup/js/predmet-sortiranje.js"></script>';

?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Tab functionality
    const tabs = document.querySelectorAll('.seup-tab');
    const tabPanes = document.querySelectorAll('.seup-tab-pane');

    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            const target = this.dataset.tab;
            if (!target) return;

            // Switch active classes for tabs and panes
            tabs.forEach(t => t.classList.remove('active'));
            tabPanes.forEach(p => p.classList.remove('active'));
            this.classList.add('active');

            const pane = document.getElementById(target);
            if (pane) pane.classList.add('active');

            // Open Omot A3 modal on "Prepregled" tab
            if (target === 'prepregled' && typeof openOmotPreview === 'function') {
                openOmotPreview();
            }
        });
    });

    // New upload buttons functionality (placeholder)
    const dodajAktBtn = document.getElementById('dodajAktBtn');
    const dodajPrilogBtn = document.getElementById('dodajPrilogBtn');
    const sortirajNedodjeljenoBtn = document.getElementById('sortirajNedodjeljenoBtn');

    if (dodajAktBtn) {
        dodajAktBtn.addEventListener('click', function() {
            aktUploadModal.classList.add('show');
        });
    }

    if (dodajPrilogBtn) {
        dodajPrilogBtn.addEventListener('click', function() {
            prilogUploadModal.classList.add('show');
        });
    }

    if (sortirajNedodjeljenoBtn) {
        sortirajNedodjeljenoBtn.addEventListener('click', function() {
            // Functionality is now handled by PredmetSortiranje class
            // The class will automatically handle this button click
        });
    }

    // Akt Upload Modal functionality
    const aktUploadModal = document.getElementById('aktUploadModal');
    const aktUploadForm = document.getElementById('aktUploadForm');
    const uploadAktBtn = document.getElementById('uploadAktBtn');
    const aktFileInput = document.getElementById('aktFile');

    function openAktModal() {
        aktUploadModal.classList.add('show');
    }

    function closeAktModal() {
        aktUploadModal.classList.remove('show');
        aktUploadForm.reset();
    }

    function uploadAkt() {
        const file = aktFileInput.files[0];
        if (!file) {
            showMessage('Molimo odaberite datoteku', 'error');
            return;
        }

        // Validate file size (max 50MB)
        if (file.size > 50 * 1024 * 1024) {
            showMessage('Datoteka je prevelika (maksimalno 50MB)', 'error');
            return;
        }

        // Show loading state
        uploadAktBtn.classList.add('seup-loading');
        const progressDiv = document.getElementById('uploadProgress');
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');
        
        progressDiv.style.display = 'block';
        progressText.textContent = 'Uploading akt...';

        // Create FormData
        const formData = new FormData(aktUploadForm);

        // Upload with progress
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                progressFill.style.width = percentComplete + '%';
                progressText.textContent = `Uploading... ${Math.round(percentComplete)}%`;
            }
        });

        xhr.addEventListener('load', function() {
            try {
                console.log('HTTP Status:', xhr.status);
                console.log('Server response:', xhr.responseText);
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    showMessage(response.message, 'success');
                    closeAktModal();
                    // Reload page to show new akt
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage('Greška pri uploadu: ' + response.error, 'error');
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response text:', xhr.responseText);
                showMessage('Greška pri obradi odgovora: ' + xhr.responseText.substring(0, 200), 'error');
            }
        });

        xhr.addEventListener('error', function() {
            showMessage('Greška pri uploadu datoteke', 'error');
        });

        xhr.addEventListener('loadend', function() {
            uploadAktBtn.classList.remove('seup-loading');
            progressDiv.style.display = 'none';
            progressFill.style.width = '0%';
        });

        xhr.open('POST', '', true);
        xhr.send(formData);
    }

    // Akt modal event listeners
    document.getElementById('closeAktModal').addEventListener('click', closeAktModal);
    document.getElementById('cancelAktBtn').addEventListener('click', closeAktModal);
    document.getElementById('uploadAktBtn').addEventListener('click', uploadAkt);

    // Close modal when clicking outside
    aktUploadModal.addEventListener('click', function(e) {
        if (e.target === this) {
            closeAktModal();
        }
    });

    // Prilog Upload Modal functionality
    const prilogUploadModal = document.getElementById('prilogUploadModal');
    const prilogUploadForm = document.getElementById('prilogUploadForm');
    const uploadPrilogBtn = document.getElementById('uploadPrilogBtn');
    const prilogFileInput = document.getElementById('prilogFile');
    const aktSelect = document.getElementById('aktSelect');

    function openPrilogModal() {
        prilogUploadModal.classList.add('show');
    }

    function closePrilogModal() {
        prilogUploadModal.classList.remove('show');
        if (prilogUploadForm) {
            prilogUploadForm.reset();
        }
    }

    function uploadPrilog() {
        if (!prilogUploadForm) {
            showMessage('Nema dostupnih akata za dodavanje priloga', 'error');
            return;
        }
        
        const file = prilogFileInput.files[0];
        const selectedAkt = aktSelect.value;
        
        if (!selectedAkt) {
            showMessage('Molimo odaberite akt', 'error');
            return;
        }
        
        if (!file) {
            showMessage('Molimo odaberite datoteku', 'error');
            return;
        }

        // Validate file size (max 50MB)
        if (file.size > 50 * 1024 * 1024) {
            showMessage('Datoteka je prevelika (maksimalno 50MB)', 'error');
            return;
        }

        // Show loading state
        uploadPrilogBtn.classList.add('seup-loading');
        const progressDiv = document.getElementById('uploadProgress');
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');
        
        progressDiv.style.display = 'block';
        progressText.textContent = 'Uploading prilog...';

        // Create FormData
        const formData = new FormData(prilogUploadForm);

        // Upload with progress
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percentComplete = (e.loaded / e.total) * 100;
                progressFill.style.width = percentComplete + '%';
                progressText.textContent = `Uploading... ${Math.round(percentComplete)}%`;
            }
        });

        xhr.addEventListener('load', function() {
            try {
                console.log('HTTP Status (prilog):', xhr.status);
                console.log('Server response (prilog):', xhr.responseText);
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    showMessage(response.message, 'success');
                    closePrilogModal();
                    // Reload page to show new prilog
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showMessage('Greška pri uploadu: ' + response.error, 'error');
                }
            } catch (e) {
                console.error('JSON parse error (prilog):', e);
                console.error('Response text:', xhr.responseText);
                showMessage('Greška pri obradi odgovora: ' + xhr.responseText.substring(0, 200), 'error');
            }
        });

        xhr.addEventListener('error', function() {
            showMessage('Greška pri uploadu datoteke', 'error');
        });

        xhr.addEventListener('loadend', function() {
            uploadPrilogBtn.classList.remove('seup-loading');
            progressDiv.style.display = 'none';
            progressFill.style.width = '0%';
        });

        xhr.open('POST', '', true);
        xhr.send(formData);
    }

    // Prilog modal event listeners
    if (document.getElementById('closePrilogModal')) {
        document.getElementById('closePrilogModal').addEventListener('click', closePrilogModal);
    }
    if (document.getElementById('cancelPrilogBtn')) {
        document.getElementById('cancelPrilogBtn').addEventListener('click', closePrilogModal);
    }
    if (document.getElementById('uploadPrilogBtn')) {
        document.getElementById('uploadPrilogBtn').addEventListener('click', uploadPrilog);
    }
    if (document.getElementById('dodajAktPrviBtn')) {
        document.getElementById('dodajAktPrviBtn').addEventListener('click', function() {
            closePrilogModal();
            dodajAktBtn.click();
        });
    }

    // Close prilog modal when clicking outside
    if (prilogUploadModal) {
        prilogUploadModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closePrilogModal();
            }
        });
    }

    // Omot generation functionality
    const generateOmotBtn = document.getElementById('generateOmotBtn');
    const previewOmotBtn = document.getElementById('previewOmotBtn');
    const printOmotBtn = document.getElementById('printOmotBtn');

    if (generateOmotBtn) {
        generateOmotBtn.addEventListener('click', function() {
            this.classList.add('seup-loading');
            
            const formData = new FormData();
            formData.append('action', 'generate_omot');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                    // Reload documents list to show new omot
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showMessage('Greška pri generiranju omota: ' + data.error, 'error');
                }
            })
            .catch(error => {
                showMessage('Došlo je do greške pri generiranju omota', 'error');
            })
            .finally(() => {
                this.classList.remove('seup-loading');
            });
        });
    }

    if (previewOmotBtn) {
        previewOmotBtn.addEventListener('click', function() {
            openOmotPreview();
        });
    }

    if (printOmotBtn) {
        printOmotBtn.addEventListener('click', function() {
            openPrintInstructionsModal();
        });
    }

    // Print Instructions Modal functionality
    function openPrintInstructionsModal() {
        const modal = document.getElementById('printInstructionsModal');
        modal.classList.add('show');
    }

    function closePrintInstructionsModal() {
        const modal = document.getElementById('printInstructionsModal');
        modal.classList.remove('show');
    }

    function confirmPrint() {
        closePrintInstructionsModal();
        
        // Show loading message
        showMessage('Priprema omot za ispis...', 'success', 2000);
        
        // Small delay then print
        setTimeout(() => {
            window.print();
        }, 500);
    }

    // Print modal event listeners
    document.getElementById('closePrintModal').addEventListener('click', closePrintInstructionsModal);
    document.getElementById('cancelPrintBtn').addEventListener('click', closePrintInstructionsModal);
    document.getElementById('confirmPrintBtn').addEventListener('click', confirmPrint);

    // Close print modal when clicking outside
    document.getElementById('printInstructionsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePrintInstructionsModal();
        }
    });

    // Omot preview modal functionality
    function openOmotPreview() {
        const modal = document.getElementById('omotPreviewModal');
        const content = document.getElementById('omotPreviewContent');
        
        modal.classList.add('show');
        content.innerHTML = '<div class="seup-loading-message"><i class="fas fa-spinner fa-spin"></i> Učitavam prepregled...</div>';
        
        const formData = new FormData();
        formData.append('action', 'preview_omot');
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = data.preview_html;
            } else {
                content.innerHTML = '<div class="seup-alert seup-alert-error">Greška pri učitavanju prepregleda: ' + data.error + '</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="seup-alert seup-alert-error">Došlo je do greške pri učitavanju prepregleda</div>';
        });
    }

    function closeOmotPreview() {
        document.getElementById('omotPreviewModal').classList.remove('show');
    }

    // Modal event listeners
    document.getElementById('closeOmotModal').addEventListener('click', closeOmotPreview);
    document.getElementById('closePreviewBtn').addEventListener('click', closeOmotPreview);

    document.getElementById('generateFromPreviewBtn').addEventListener('click', function() {
        closeOmotPreview();
        generateOmotBtn.click();
    });

    // Close modal when clicking outside
    document.getElementById('omotPreviewModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeOmotPreview();
        }
    });

    // Document deletion functionality
    let currentDeleteData = null;

    document.addEventListener('click', function(e) {
        if (e.target.closest('.delete-document-btn')) {
            const btn = e.target.closest('.delete-document-btn');
            const filename = btn.dataset.filename;
            const filepath = btn.dataset.filepath;
            
            currentDeleteData = { filename, filepath };
            
            // Update modal content
            document.getElementById('deleteDocName').textContent = filename;
            
            // Show modal
            document.getElementById('deleteDocModal').classList.add('show');
        }
    });

    function closeDeleteModal() {
        document.getElementById('deleteDocModal').classList.remove('show');
        currentDeleteData = null;
    }

    function confirmDelete() {
        if (!currentDeleteData) return;
        
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        confirmBtn.classList.add('seup-loading');
        
        const formData = new FormData();
        formData.append('action', 'delete_document');
        formData.append('filename', currentDeleteData.filename);
        formData.append('filepath', currentDeleteData.filepath);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
                closeDeleteModal();
                // Reload page to update document list
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showMessage('Greška pri brisanju: ' + data.error, 'error');
            }
        })
        .catch(error => {
            showMessage('Došlo je do greške pri brisanju dokumenta', 'error');
        })
        .finally(() => {
            confirmBtn.classList.remove('seup-loading');
        });
    }

    // Delete modal event listeners
    document.getElementById('closeDeleteModal').addEventListener('click', closeDeleteModal);
    document.getElementById('cancelDeleteBtn').addEventListener('click', closeDeleteModal);
    document.getElementById('confirmDeleteBtn').addEventListener('click', confirmDelete);

    // Close delete modal when clicking outside
    document.getElementById('deleteDocModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });

    // Toast message function
    window.showMessage = function(message, type = 'success', duration = 5000) {
        let messageEl = document.querySelector('.seup-message-toast');
        if (!messageEl) {
            messageEl = document.createElement('div');
            messageEl.className = 'seup-message-toast';
            document.body.appendChild(messageEl);
        }

        messageEl.className = `seup-message-toast seup-message-${type} show`;
        messageEl.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
            ${message}
        `;

        setTimeout(() => {
            messageEl.classList.remove('show');
        }, duration);
    };
});
</script>



<?php
llxFooter();
$db->close();
?>