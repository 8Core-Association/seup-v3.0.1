# Plan razvoja: Otprema i Zaprimanje dokumenata

## 📋 Rezime konverzacije

### Što je napravljeno u ovoj sesiji:
1. **Refaktoring `predmet.php`** - reducirana velicina sa ~890 na 160 linija
2. **Ekstrakcija klasa:**
   - `PredmetDataLoader` - ucitavanje podataka
   - `PredmetActionHandler` - obrada akcija (zatvori, otvoreno, archive)
   - `PredmetView` - prikaz HTML sadrzaja
3. **Kompletna funkcionalnost sacuvana** - samo reorganizacija koda
4. **Identifikacija potrebe** - otprema i zaprimanje dokumentacije

---

## 🎯 Cilj: Dodavanje funkcionalnosti Otprema/Zaprimanje

### Trenutna situacija:
- Postoji modul **Prilozi** - upload akata i priloga na predmet
- **Nedostaje:**
  - Registracija odlaznih dokumenata (otprema)
  - Registracija dolaznih dokumenata (zaprimanje)
  - Evidencija OMAT broja, primatelja, posiljatelja

---

## 🏗️ Arhitekturne opcije

### **OPCIJA A: 3 TABA (Kompaktna verzija)**

```
[Dokumenti] [Prepregled] [Statistike]
```

**Tab "Dokumenti" sadrzaj:**
- Sekcija 1: **Akti i prilozi** (postojece)
- Sekcija 2: **Otprema** (NOVO)
  - Gumb: "Registriraj otpremu"
  - Tablica: popis svih otpremljenih dokumenata
- Sekcija 3: **Zaprimanje** (NOVO)
  - Gumb: "Registriraj zaprimanje"
  - Tablica: popis svih zaprimljenih dokumenata

**Prednosti:**
- Sve dokumenti na jednom mjestu
- Manje klikanja izmedju tabova
- Lakse pregled sveukupne dokumentacije

**Mane:**
- Tab moze biti pretrpan sadrzajem
- Vise scrollanja

---

### **OPCIJA B: 5 TABOVA (Modularna verzija)**

```
[Prilozi] [Otprema] [Zaprimanje] [Prepregled] [Statistike]
```

**Tab "Prilozi":**
- Upload akata i priloga (postojece)

**Tab "Otprema":**
- Gumb: "Registriraj otpremu"
- Tablica: odlazni dokumenti
- Forma: OMAT, primatelj, datum, nacin otpreme

**Tab "Zaprimanje":**
- Gumb: "Registriraj zaprimanje"
- Tablica: dolazni dokumenti
- Forma: OMAT, posiljatelj, datum, nacin zaprimanja

**Tab "Prepregled":**
- Omot spisa (postojece)

**Tab "Statistike":**
- Brojevi (postojece)

**Prednosti:**
- Jasna separacija funkcionalnosti
- Cistiji UI - svaki tab ima jednu svrhu
- Lakse odrzavanje koda

**Mane:**
- Vise klikanja izmedju tabova
- Potencijalno vise navigacije

---

## 📊 Struktura podataka (draft)

### Tablica: `a_otprema`
```sql
- id
- fk_predmet (foreign key -> seup_predmet)
- omat_broj (generiran ili unesen)
- primatelj (tekst ili foreign key -> suradnici)
- datum_otpreme
- nacin_otpreme (posta, mail, rucno, ...)
- napomena
- fk_prilog (opciono - link na dokument)
- fk_user (tko je registrirao)
- datec (timestamp)
```

### Tablica: `a_zaprimanje`
```sql
- id
- fk_predmet (foreign key -> seup_predmet)
- broj_posiljatelja (npr. CLASS-100/2025-01-01)
- posiljatelj (tekst ili foreign key -> suradnici)
- datum_zaprimanja
- nacin_zaprimanja (posta, mail, rucno, ...)
- napomena
- fk_prilog (opciono - link na dokument)
- fk_user (tko je registrirao)
- datec (timestamp)
```

---

## 🔄 Workflow (za razraditi)

### Otprema:
1. Korisnik otvara predmet
2. Klikne "Registriraj otpremu"
3. Unese podatke:
   - Primatelj (autocomplete iz suradnika?)
   - Datum otpreme
   - Nacin otpreme (dropdown)
   - Opciono: odabere postojeci prilog ILI uploada novi dokument
4. Sustav generira OMAT broj (ako nije unesen)
5. Zapis se sprema u bazu

### Zaprimanje:
1. Korisnik otvara predmet
2. Klikne "Registriraj zaprimanje"
3. Unese podatke:
   - Posiljatelj (autocomplete?)
   - Broj posiljatelja (npr. njihov interni broj)
   - Datum zaprimanja
   - Nacin zaprimanja
   - Opciono: upload dokumenta
4. Zapis se sprema u bazu

---

## ❓ Pitanja za razraditi

### 1. **Integracija s OMAT generatorom:**
- Koristiti postojeci `omat_generator.class.php`?
- Razlika izmedju OMAT-a akata i OMAT-a otpreme?

### 2. **Povezanost s prilozima:**
- Moze li se dokument istovremeno biti prilog I otprema?
- Treba li odvojiti tablice ili koristiti zastavice (flags)?

### 3. **Suradnici (primatelji/posiljatelji):**
- Koristiti postojecu tablicu suradnika?
- Dodati autocomplete za brzo unosenje?

### 4. **Nacini otpreme/zaprimanja:**
- Hardkodirani dropdown ili konfiguracijska tablica?
- Primjeri: Posta, E-mail, Rucno, Courier, Fax, ...

### 5. **Pristupna prava:**
- Tko moze registrirati otpremu/zaprimanje?
- Tko moze vidjeti povijest?

### 6. **UI/UX:**
- Modal prozor ili zasebna stranica?
- Inline edit u tablici ili forma?

### 7. **Izvjestaji:**
- Treba li izvjestaj svih otprema po datumu?
- Treba li izvjestaj svih zaprimanja?
- Integracija sa statistikama?

---

## 🚀 Sljedeci koraci (kad se vrati na projekt)

### FAZA 1: Planiranje
- [ ] Napraviti workflow dijagram (kako korisnici rade u stvarnom zivotu)
- [ ] Definirati tocna polja za forme
- [ ] Odluciti izmedju Opcije A ili B
- [ ] Skicirati UI (wireframes)

### FAZA 2: Baza podataka
- [ ] Kreirati migration za tablice `a_otprema` i `a_zaprimanje`
- [ ] Dodati foreign key constrainte
- [ ] Testirati na testnim podacima

### FAZA 3: Backend
- [ ] Kreirati klase:
  - `otprema_helper.class.php`
  - `zaprimanje_helper.class.php`
- [ ] Implementirati CRUD operacije
- [ ] Integrirati s OMAT generatorom

### FAZA 4: Frontend
- [ ] Dodati tabove (ili sekcije)
- [ ] Kreirati forme za unos
- [ ] Dodati tablice za prikaz
- [ ] CSS stilizacija

### FAZA 5: Testiranje
- [ ] Unit testovi za klase
- [ ] Manualno testiranje workflow-a
- [ ] Testiranje na razlicitim razinama prava

---

## 📌 Napomene

- **Kod je refaktoriran** - predmet.php sada 160 linija (bilo 890)
- **Postojece funkcionalnosti rade** - zatvaranje, otvaranje, arhiviranje
- **Projekt je u bookmarku** - ali chat history se gubi
- **Ova datoteka cuva kontekst** - procitaj je na pocetku sljedece sesije

---

## 🔗 Poveznice na vazne fileove

- **Glavni file:** `/seup/pages/predmet.php` (160 linija)
- **JavaScript:** `/seup/js/predmet.js`
- **CSS:** `/seup/css/predmet.css`
- **Klase:**
  - `/seup/class/predmet_data_loader.class.php`
  - `/seup/class/predmet_action_handler.class.php`
  - `/seup/class/predmet_view.class.php`
  - `/seup/class/request_handler.class.php`
- **Helperi:**
  - `/seup/class/omat_generator.class.php`
  - `/seup/class/prilog_helper.class.php`

---

**Verzija:** 1.0
**Datum:** 2025-11-19
**Status:** Planirano - nije implementirano
