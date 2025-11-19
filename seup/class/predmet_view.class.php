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

class Predmet_View
{
    public static function printHeader($predmet)
    {
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
    }

    public static function printCaseDetails($predmet)
    {
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

        print '</div>';
        print '</div>';
    }

    public static function printTabs()
    {
        print '<div class="seup-tabs">';
        print '<button class="seup-tab active" data-tab="prilozi"><i class="fas fa-paperclip"></i>Prilozi</button>';
        print '<button class="seup-tab" data-tab="prepregled"><i class="fas fa-eye"></i>Prepregled</button>';
        print '<button class="seup-tab" data-tab="statistike"><i class="fas fa-chart-bar"></i>Statistike</button>';
        print '</div>';
    }

    public static function printPriloziTab($documentTableHTML)
    {
        print '<div class="seup-tab-pane active" id="prilozi">';

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

        print '<div class="seup-documents-header">';
        print '<h5 class="seup-documents-title"><i class="fas fa-paperclip"></i>Akti i Prilozi Predmeta</h5>';
        print '</div>';

        print $documentTableHTML;

        print '</div>';
    }

    public static function printPrepregledTab()
    {
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
        print '</div>';
    }

    public static function printStatistikeTab($predmet, $doc_count)
    {
        $vrijeme_text = ($predmet->vrijeme_cuvanja == 0) ? 'Trajno' : $predmet->vrijeme_cuvanja . ' godina';

        print '<div class="seup-tab-pane" id="statistike">';
        print '<div class="seup-stats-container">';
        print '<h5><i class="fas fa-chart-bar"></i>Statistike Predmeta</h5>';
        print '<div class="seup-stats-grid">';

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

        print '</div>';
        print '</div>';
        print '</div>';
    }

    public static function printModals($caseId, $availableAkti)
    {
        self::printOmotPreviewModal();
        self::printPrintInstructionsModal();
        self::printDeleteDocumentModal();
        self::printAktUploadModal($caseId);
        self::printPrilogUploadModal($caseId, $availableAkti);
    }

    private static function printOmotPreviewModal()
    {
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
    }

    private static function printPrintInstructionsModal()
    {
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

        print '</div>';
        print '</div>';
        print '<div class="seup-modal-footer">';
        print '<button type="button" class="seup-btn seup-btn-secondary" id="cancelPrintBtn">Odustani</button>';
        print '<button type="button" class="seup-btn seup-btn-primary" id="confirmPrintBtn">';
        print '<i class="fas fa-print me-2"></i>Ispiši Omot';
        print '</button>';
        print '</div>';
        print '</div>';
        print '</div>';
    }

    private static function printDeleteDocumentModal()
    {
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
    }

    private static function printAktUploadModal($caseId)
    {
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
    }

    private static function printPrilogUploadModal($caseId, $availableAkti)
    {
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
    }

    public static function printScripts()
    {
        print '<script src="/custom/seup/js/seup-modern.js"></script>';
        print '<script src="/custom/seup/js/predmet-sortiranje.js"></script>';
        print '<script src="/custom/seup/js/predmet.js"></script>';
    }
}
