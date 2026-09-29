# GKR Klantportaal

Het GKR Klantportaal is een veilig, overzichtelijk platform gebouwd met het Laravel-framework. Dit portaal fungeert als de centrale brug tussen de organisatie GKR en haar klanten. Het stelt klanten in staat om de status van hun projecten in te zien, documenten te beheren, opmerkingen te plaatsen en afspraken in te plannen.

## Inhoudsopgave
1. Functionaliteiten
2. Systeemeisen
3. Installatie & Lokale Setup
4. Rollen en Rechten
5. Projectstructuur
6. Deployment (Railway)
7. White-label branding (API)
8. Afspraken en Outlook-koppeling

---

## 1. Functionaliteiten

Het platform is opgebouwd rondom een aantal kernmodules om de samenwerking met de klant soepel te laten verlopen:

*   **Multi-tenant Projectbeheer:** Klanten zien uitsluitend de projecten en gegevens die aan hun eigen account zijn gekoppeld.
*   **Documentenbeheer:** Veilige opslag en overdracht van projectdocumenten (zoals handleidingen, rapportages of ontwerpen).
*   **Interactiesysteem:** Mogelijkheid voor zowel de klant als de administrator om opmerkingen en feedback achter te laten bij specifieke projecten of documenten.
*   **Afsprakensysteem:** Klanten kunnen direct beschikbare datums en tijden inzien en een afspraak inplannen met GKR.
*   **Wachtwoordbeveiliging (Site-wide):** Extra beveiligingslaag via `SitePasswordProtection` middleware om de applicatie in test- of stagingomgevingen af te schermen voor onbevoegden.

---

## 2. Systeemeisen

Zorg ervoor dat de volgende software op je lokale machine is geïnstalleerd:

*   PHP (versie 8.2 of hoger aanbevolen)
*   Composer (voor PHP package management)
*   Node.js & NPM (voor het compileren van de frontend assets)
*   Een database (zoals MySQL, PostgreSQL of SQLite)

---

## 3. Installatie & Lokale Setup

Volg deze stappen om het project lokaal op te starten:

**Stap 1: Clone de repository**
```bash
git clone <repository-url>
cd Klantportaal-GKR
```

**Stap 2: Installeer de PHP-dependencies**
```bash
composer install
```

**Stap 3: Installeer de Frontend-dependencies**
```bash
npm install
```

**Stap 4: Omgevingsvariabelen instellen**
Kopieer het voorbeeld-omgevingsbestand naar een live `.env` bestand:
```bash
cp .env.example .env
```
Open het `.env` bestand en vul je databasegegevens in (zoals `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).

**Stap 5: Genereer de applicatiesleutel**
```bash
php artisan key:generate
```

**Stap 6: Database migraties en seeders uitvoeren**
Maak de tabellen aan en vul de database met eventuele testgegevens:
```bash
php artisan migrate --seed
```

**Stap 7: Applicatie lokaal starten**
Start de lokale PHP-ontwikkelserver:
```bash
php artisan serve
```
Start in een apart terminalvenster Vite op om de CSS- en JavaScript-bestanden live te compileren:
```bash
npm run dev
```
Je kunt het portaal nu bezoeken via `http://127.0.0.1:8000`.

---

## 4. Rollen en Rechten

Het platform kent twee primaire gebruikersrollen die bepalen wat een gebruiker mag zien en doen:

1.  **Administrator (GKR Team)**
    *   Heeft toegang tot het volledige admin-dashboard (`/admin/dashboard`).
    *   Kan nieuwe klanten, projecten en afspraakopties aanmaken en beheren.
    *   Kan documenten uploaden en koppelen aan specifieke klantprojecten.
2.  **Klant (Client)**
    *   Heeft uitsluitend toegang tot de eigen klantomgeving.
    *   Kan de status en details van het eigen project bekijken.
    *   Kan gekoppelde documenten inzien/downloaden en feedback achterlaten via opmerkingen.
    *   Kan zelfstandig afspraken inplannen op basis van de door de admin klaargezette opties.

---

## 5. Projectstructuur

De belangrijkste onderdelen van deze applicatie bevinden zich op de volgende plekken:

*   **`app/Models/`**: Bevat de databasemodellen en hun onderlinge relaties (`User`, `Project`, `Document`, `Comment`, `Appointment`, `AppointmentOption`).
*   **`app/Http/Controllers/`**: Bevat de logica van de schermen. Netjes opgesplitst in een `Admin/` map voor de GKR-beheerder en een `Client/` map voor de klantfuncties.
*   **`app/Http/Middleware/`**: Bevat beveiligingsfilters zoals `IsAdmin` (controleert of de gebruiker een administrator is) en `SitePasswordProtection` (voorziet de hele site van een algemeen toegangswachtwoord).
*   **`database/migrations/`**: De blauwdrukken van de database tabellen (inclusief tabellen voor projecten, documenten, opmerkingen en afspraken).
*   **`resources/views/`**: De visuele schermen van de applicatie, gebouwd met Blade-templates en gestyled met Tailwind CSS.

---

## 6. Deployment (Railway)

Dit project is voorbereid om eenvoudig te worden uitgerold via **Railway.app**. 

*   In de map `railway/` bevindt zich het script `init-app.sh`. Dit script zorgt ervoor dat tijdens het opstarten op het cloudplatform automatisch de juiste stappen worden gezet (zoals het optimaliseren van de configuratie en het veilig uitvoeren van database-migraties).
*   Zorg ervoor dat bij het instellen van de omgevingsvariabelen op Railway de database-koppeling correct naar de gekoppelde Railway database-dienst verwijst.
*   **Queue-worker (sinds de Outlook-koppeling nodig):** maak een tweede Railway-service van dezelfde repo met startcommando `sh railway/worker.sh` en dezelfde omgevingsvariabelen. Zonder worker worden afspraken niet in Outlook gezet (ze blijven op "Wordt nu in Outlook gezet" staan). Zie sectie 8.

---

## 7. White-label branding (API)

Het portaal kan onder de naam en huisstijl van een andere organisatie worden aangeboden, zonder codewijziging (FR-02 / NFR-03). Ontwerp en afwegingen: **ADR-010** in `StageProject/docs/adr/`.

*   **Eén brandingrecord per installatie** (`brandings`-tabel, model `App\Models\Branding`). `klant_id` is voorbereid maar nog niet in gebruik; per-klant branding volgt met het Klant-model (#33).
*   **Defaults** staan in `config/branding.php` (`Klantportaal`, `#011936`, `#059669`, geen logo). Een installatie zonder configuratie toont dus geen merknaam. De organisatie stelt haar eigen naam, kleuren en logo in vanuit de app (Profiel → Huisstijl).
*   **Endpoints:**

    | Methode | Pad | Toegang | Doel |
    |---|---|---|---|
    | `GET` | `/api/branding` | publiek (throttle 60/min) | huidige branding, of de defaults; nodig op het inlogscherm |
    | `PUT` | `/api/branding` | admin | `organization_name`, `primary_color`, `accent_color` (volledig) |
    | `POST` | `/api/branding/logo` | admin | multipart-veld `logo` |
    | `DELETE` | `/api/branding/logo` | admin | logo verwijderen (terug naar default) |

    Antwoord: `{organization_name, primary_color, accent_color, logo_path, updated_at}`. `logo_path` is root-relatief (`/storage/branding/default/<hash>.png`); clients zetten het achter hun eigen base-URL.
*   **Validatie bij opslaan:** naam 2–40 tekens (letters, cijfers en `. , & ' -`), kleuren strikt `#RRGGBB`, en WCAG-contrast: primair ↔ wit ≥ 4,5:1 en accent ↔ wit ≥ 3:1. Logo: PNG/JPG/WebP, max. 2 MB, 64–2000 px; **SVG wordt geweigerd**. Fouten komen terug als 422 met een melding in gewone taal die zegt welke kant de kleur op moet (bijv. "Kies een donkerdere primaire kleur."); zonder verhoudingen of normcodes.
*   **Autorisatie:** `auth:sanctum` + `admin`-middleware (geeft voor API-requests een JSON-403 in plaats van een redirect) + `BrandingPolicy`.
*   **Lokaal:** `php artisan migrate` en eenmalig `php artisan storage:link` (voor het serveren van logo's). De login-response bevat nu ook `user.is_admin`.
*   **Railway:** het containerfilesystem is vluchtig; zonder volume op `storage/` verdwijnt een geüpload logo bij een deploy en valt de app terug op "geen logo".
*   **Nog niet gebrand:** de web-app (Tailwind-kleuren, Blade-layouts, logo-component), e-mails en ICS-exports gebruiken nog vaste GKR-waarden. Die volgen in een vervolg-issue, via dezelfde `Branding::current()`.

---

## 8. Afspraken en Outlook-koppeling

Bevestigde afspraken komen automatisch in Outlook, en het platform kijkt in Outlook wie er beschikbaar is (FR-08). Ontwerp en afwegingen: **ADR-011** in `StageProject/docs/adr/`.

### Hoe het werkt
*   **Eén koppeling voor heel GKR** (Microsoft Graph, applicatierechten). Medewerkers hoeven niets te koppelen; klanten ook niet.
*   **Richting: platform → Outlook.** Wijzigingen die iemand in Outlook zelf maakt, komen niet terug in het platform.
*   Bij **Bevestigd** maakt het platform een afspraak in de agenda van de **organiserende medewerker** (wie het voorstel deed, of bij een klantaanvraag de eerst gekozen medewerker). Outlook stuurt zelf de uitnodiging, vanaf diens werkmail, naar de klant en de andere medewerkers. **Online** krijgt een Teams-link.
*   `CALENDAR_OVERVIEW_MAILBOX` (bijv. `info@gkr.nl`) staat als optionele deelnemer op elke afspraak. Daar wordt de kopie stil geaccepteerd en gekleurd per medewerker (Outlook-categorie).
*   **Op locatie** met reistijd: aparte "Reistijd"-blokken vóór en na de afspraak in de agenda's van de betrokken medewerkers.
*   **Geannuleerd**: de afspraak wordt in Outlook geannuleerd (deelnemers krijgen een annulering) en de reistijdblokken verdwijnen.
*   **Beschikbaarheid:** werktijden uit `config/appointments.php` (ma–vr 09:00–17:00, blokken van een uur), min vastgelegde afspraken, min Outlook (bezet, voorlopig, afwezig), min gesloten dagen (`closed_days`, beheerd door admins). Vakanties zet een medewerker zelf in Outlook als **Afwezig**.
*   **Dubbele boekingen:** bij het definitief vastleggen neemt de server per medewerker een lock en controleert opnieuw (vers uit Outlook). Wie als tweede komt, krijgt 409 met een melding in gewone taal.
*   De sync draait in de wachtrij (`SyncAppointmentToCalendar`), met opnieuw proberen bij tijdelijke fouten. Lukt het niet, dan staat `calendar_sync_status` op `failed` en ziet de admin dat in de app.

### Instellen in Microsoft Entra (eenmalig, door een beheerder)
1.  **App registreren:** Entra-beheercentrum → *App-registraties* → *Nieuwe registratie*, naam `Klantportaal`, alleen accounts in deze organisatie. Noteer *Directory (tenant) ID* en *Application (client) ID*.
2.  **Rechten:** *API-machtigingen* → *Microsoft Graph* → **Toepassingsmachtigingen**: `Calendars.ReadWrite` en `MailboxSettings.ReadWrite` (voor de kleuren in de overzichtsagenda). Klik **Beheerderstoestemming verlenen**.
3.  **Geheim:** *Certificaten en geheimen* → nieuw clientgeheim. Kopieer de waarde direct. **Zet een herinnering**: het geheim verloopt (maximaal 24 maanden); daarna stopt de sync tot er een nieuw geheim in Railway staat.
4.  **Toegang beperken tot de juiste mailboxen:** maak een e-mailbeveiligde groep `Klantportaal-agenda's` met info@ en de medewerkersmailboxen, en beperk de app daartoe via Exchange Online PowerShell (RBAC for Applications):
    ```powershell
    New-ServicePrincipal -AppId <client-id> -ObjectId <enterprise-app-object-id> -DisplayName "Klantportaal"
    New-ManagementScope -Name "Klantportaal-agendas" -RecipientRestrictionFilter "MemberOfGroup -eq '<DN van de groep>'"
    New-ManagementRoleAssignment -App <client-id> -Role "Application Calendars.ReadWrite" -CustomResourceScope "Klantportaal-agendas"
    New-ManagementRoleAssignment -App <client-id> -Role "Application MailboxSettings.ReadWrite" -CustomResourceScope "Klantportaal-agendas"
    ```
    Haal daarna de tenant-brede Graph-toestemmingen uit stap 2 weer weg, zodat alleen de beperkte Exchange-rol overblijft. Zonder deze stap kan de app bij **alle** mailboxen in de organisatie.
5.  **Omgevingsvariabelen** (lokaal in `.env`, op Railway in beide services):
    ```
    CALENDAR_DRIVER=graph
    CALENDAR_OVERVIEW_MAILBOX=info@gkr.nl
    MICROSOFT_TENANT_ID=...
    MICROSOFT_CLIENT_ID=...
    MICROSOFT_CLIENT_SECRET=...
    ```
6.  **Controleren:** `php artisan calendar:check`. Dit laat per medewerker zien of de koppeling bij de agenda kan. Het e-mailadres van een admin in het platform moet gelijk zijn aan het Microsoft 365-adres.

### Lokaal en in tests
*   Standaard is `CALENDAR_DRIVER=fake`: er wordt nooit een echte agenda aangeroepen. Tests gebruiken `FakeCalendarProvider` en `Http::fake()`.
*   Draai `php artisan queue:work` (of `composer dev`) om de sync lokaal te zien werken.
*   Testgegevens: `php artisan db:seed --class=AppointmentDemoSeeder`.

### API (mobiele app, `auth:sanctum`)
| Methode | Pad | Toegang | Doel |
|---|---|---|---|
| `GET` | `/api/appointments` | klant | eigen afspraken |
| `GET` | `/api/appointments/{id}` | klant (eigen) / admin | één afspraak (vreemd id → 404) |
| `POST` | `/api/appointments` | klant | moment aanvragen (`type`, `location`, `project_id`, `title`, `description`, `employee_ids[1-2]`, `start_time`) |
| `POST` | `/api/appointments/{id}/confirm-option` | klant | voorgesteld moment kiezen (`option_id`) |
| `POST` | `/api/appointments/{id}/alternative` | klant | zelf een ander moment kiezen (`start_time`) |
| `POST` | `/api/appointments/{id}/cancel` | klant | annuleren |
| `GET` | `/api/availability` | ingelogd | vrije blokken (`employee_ids[]`, `from`, `to`; admin ook `duration_minutes`, `travel_minutes`) |
| `GET` | `/api/employees` | ingelogd | GKR-medewerkers met agendakleur |
| `GET` | `/api/projects` | klant | eigen projecten |
| `POST` | `/api/callback-requests` | klant | belverzoek (`phone`, `note`, `project_id`) |
| `GET` | `/api/contact` | ingelogd | telefoonnummer en "Nu beschikbaar" |
| `GET` | `/api/admin/appointments` | admin | alle of eigen afspraken (`scope=mine\|all`, `from`, `to`) |
| `POST` | `/api/admin/appointments` | admin | voorstel met 1–3 `options` |
| `POST` | `/api/admin/appointments/{id}/approve` · `/reject` | admin | definitief bevestigen / afwijzen |
| `GET` | `/api/admin/availability` | admin | status per medewerker per moment, met waarschuwingen |
| `GET` | `/api/admin/clients` · `/api/admin/clients/{id}/projects` | admin | klanten en hun projecten |
| `PATCH` | `/api/admin/employees/{id}` | admin | agendakleur (`calendar_color`, Outlook-preset) |
| `GET`/`PATCH` | `/api/admin/callback-requests[/{id}]` | admin | belverzoeken |
| `GET`/`POST`/`DELETE` | `/api/admin/closed-days[/{id}]` | admin | dagen waarop GKR gesloten is |
| `PATCH` | `/api/me/preferences` | admin | `agenda_scope` (`mine`/`all`), dezelfde keuze als het vinkje op de website |

Fouten uit het afsprakendomein komen terug als `{"status": "error", "message": "..."}` (409 bij een bezet moment, 422 bij een ongeldige stap), altijd in gewone taal.

### Wat er met deze wijziging ook is opgelost
*   De `.ics`-download stond buiten de login en was voor elke afspraak op id op te vragen; nu alleen voor de klant zelf en admins.
*   Bevestigen of een alternatief kiezen controleerde niet of de afspraak van de klant was.
*   Een klant kon een afspraak aanvragen op het project van een andere klant.
*   `.ics`-tijden gebruikten de maand in plaats van de seconden en waren niet naar UTC omgezet.
*   Het vaste sandbox-e-mailadres in de admin-controller is vervangen door `APPOINTMENT_MAIL_ENABLED` / `APPOINTMENT_MAIL_ONLY_TO` (standaard: geen mail).

