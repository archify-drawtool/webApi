# Migratie naar Microsoft Entra ID (voorheen Azure AD)

Vervanging van Laravel Sanctum door Microsoft Entra ID als identity provider
voor de Laravel API en de Flutter-app.

---

## 1. Doel & aanpak

De opdrachtgever beheert gebruikers in Microsoft Entra ID. In plaats van dat
wij een eigen login-scherm en wachtwoord-tabel bijhouden, laten we gebruikers
rechtstreeks bij Microsoft inloggen en vertrouwen wij het token dat Microsoft
uitgeeft.

Technisch: **OAuth 2.0 / OpenID Connect — Authorization Code flow met PKCE**.
De Flutter-app doet de login, ontvangt een access token, en stuurt dat
token als `Authorization: Bearer …` naar de Laravel API. De API valideert
de signature van het token via de publieke JWKS-endpoints van Microsoft.

---

## 2. Wat hebben we van de opdrachtgever nodig?

Graag het onderstaande per mail of via een veilige kanaal. Hun IT-/Azure-
beheerder kan dit binnen ~30 min regelen in Entra ID > App registrations.

### 2.1 Identiteit van de tenant
- **Tenant ID** (GUID, bijv. `72f988bf-86f1-41af-91ab-2d7cd011db47`) — te
  vinden in het Azure-portal onder *Microsoft Entra ID > Overview*.
- **Primaire tenant-domein** (bijv. `klantbedrijf.onmicrosoft.com` of
  `klantbedrijf.nl`) — puur informatief, voor de config-regels.

### 2.2 App registration — door *hun* Azure-beheerder aan te maken
Ze maken één App registration aan in hún tenant, met de volgende instellingen:

| Instelling                | Waarde                                                   |
|---------------------------|----------------------------------------------------------|
| Naam                      | bijv. `<AppNaam> – Mobile + API`                         |
| Supported account types   | **Single tenant** (alleen hun organisatie)               |
| Platform                  | *Mobile and desktop applications*                        |
| Redirect URI (Flutter)    | Custom scheme, bv. `msauth.nl.wolfmeister.app://auth`    |
| Redirect URI (iOS bundle) | `msauth.<iOS-bundle-id>://auth`                          |
| Allow public client flows | **Yes** (vereist voor mobile + PKCE)                     |
| Expose an API             | App ID URI `api://<client-id>`, scope `access_as_user`    |

Ze leveren ons daarna aan:
- **Application (client) ID** — GUID.
- **API scope-naam** — bv. `api://<client-id>/access_as_user`.
- Optioneel: de **App ID URI** als ze daarvan afwijken.

### 2.3 Toegangsbeleid
Iedereen in de tenant mag inloggen — geen assignment required, geen
groepsrestrictie.

### 2.4 Rollen
App Roles worden in de App registration gedefinieerd: **`Admin`** en
**`Editor`**. De beheerder wijst deze rollen toe aan gebruikers/groepen;
ze komen mee in het token als `roles`-claim.

### 2.5 Eventuele extras
- Moet er **nog een fallback admin-login** blijven bestaan voor
  noodgevallen (service-account) of juist niet?
- Geldt er een **Conditional Access-policy** die mobile apps kan blokkeren?
  (device compliance, geo-fencing, enz.) — goed om vooraf te weten.
- Logging/audit-eisen: willen zij de sign-ins in hun Entra-audit log
  zien? (Gratis, maar goed te bevestigen.)

---

## 3. Nog te nemen beslissingen (aan onze kant, eventueel in overleg)

1. **Wel of geen lokale `users`-tabel behouden?**
   Ja — behouden als "shadow user" tabel. We hebben hem nodig voor FK's
   (`projects.created_by` → `users.id`). Wachtwoord-kolom wordt verwijderd
   of nullable; we voegen `azure_oid` en `tenant_id` toe.

2. **JIT-provisioning vs. pre-provisioned**
   JIT (just-in-time) is het simpelst: bij eerste login automatisch een
   user-record aanmaken op basis van de token-claims. Aanbevolen tenzij er
   een specifieke reden is voor pre-provisioning.

3. **Token-validatie library**
   - `firebase/php-jwt` + zelf JWKS-cache (licht, expliciet — aanbevolen).
   - `socialiteproviders/microsoft-azure` (voegt meer af).
   - `kingsoft/laravel-azure-ad` en vergelijkbare community-packages
     (wisselende onderhoudskwaliteit).

4. **Microsoft Graph ja/nee**
   Nodig als we naast "wie is dit" ook foto, groepslidmaatschap, managers,
   etc. willen ophalen. Voor de huidige app-functies (projecten + sketches)
   niet nodig — kan later.

5. **Single- vs. multi-tenant**
   Voorlopig **single-tenant** (alleen de opdrachtgever). Als er ooit
   meerdere organisaties bij moeten, is omzetten naar multi-tenant
   een kleine config-aanpassing.

6. **Token lifetime**
   Microsoft default: access token ~1 uur, refresh token 24–90 dagen.
   Flutter-client moet stille refresh netjes afhandelen. Sanctums oneindige
   tokens verdwijnen — dat is een *verbetering*, geen issue.

7. **Logout-semantiek**
   Lokale logout = token weggooien in de app. "Echt uitloggen" bij Microsoft
   vereist een extra call naar het `end_session` endpoint van Entra. Willen
   we dat?

---

## 4. Wat verandert er in de Laravel-code (grof)

**Weg:**
- `laravel/sanctum` composer-dep
- `Laravel\Sanctum\HasApiTokens` trait op `User`
- `personal_access_tokens` tabel (migration + data)
- `AuthController::login()` (email/password afhandeling)
- `password` kolom in users-tabel (of nullable maken)
- `config/sanctum.php`

**Toegevoegd / aangepast:**
- Composer: `firebase/php-jwt` (JWT + JWKS parsing).
- Nieuwe middleware/guard `auth:azure` die:
  1. Bearer token uit header leest
  2. JWKS ophaalt van `https://login.microsoftonline.com/{tenant}/discovery/v2.0/keys` (gecached in Laravel cache).
  3. Signature, `iss`, `aud`, `exp`, `nbf` valideert.
  4. User opzoekt in `users`-tabel op `azure_oid`; niet gevonden → JIT-create.
  5. `Auth::setUser(...)` zodat `$request->user()` in de rest van de app gewoon werkt.
- Migration: `users` krijgt `azure_oid` (string, unique, nullable), `azure_tenant_id` (string, nullable); `password` wordt nullable.
- `routes/api.php`: `auth:sanctum` → `auth:azure`.
- `config/services.php`: `azure` entry met `tenant`, `client_id`, `api_audience`.
- `.env(.example)`: `AZURE_TENANT_ID`, `AZURE_CLIENT_ID`, `AZURE_API_AUDIENCE`.

**Raakt niet:**
- Project/Sketch/Photo controllers en services — die gebruiken allemaal
  `$request->user()`, dat blijft werken.
- Database-schema van de businessdomein-tabellen.

---

## 5. Wat verandert er in de Flutter-app

We hebben die code nog niet bij de hand, maar op hoofdlijnen:
- Library kiezen: `flutter_appauth` (platform-agnostisch OIDC) of
  `aad_oauth` / `msal_auth` (MSAL-wrapper, meer Microsoft-specifiek).
- Android: `AndroidManifest.xml` intent-filter voor het redirect-scheme.
- iOS: `Info.plist` `CFBundleURLTypes` entry + `LSApplicationQueriesSchemes`.
- Huidige email/password-screen vervangen door één "Inloggen met Microsoft" knop.
- Token opslag: `flutter_secure_storage` (Keychain/Keystore).
- Refresh-token flow: library regelt meestal automatisch.

**Conclusie: we hebben de Flutter-code nodig om de migratie aan die kant
uit te werken en te testen, maar de backend-architectuur is onafhankelijk
te ontwerpen en vooruit te werken.**

---

## 6. Was de oude user story voldoende?

Eerlijk: **nee**, niet voor een implementeerbaar ticket. Wat typisch
ontbreekt (en hier ook ontbreekt):

- Onderscheid Entra ID vs. Entra External ID (B2C) — levert compleet
  andere configuratie op.
- Single- vs. multi-tenant keuze.
- Concrete scope/audience-definitie (custom API-scope vs. MS Graph).
- Gebruikersmigratie-strategie (hoe bestaande projecten behouden blijven).
- Rolmodel (App Roles vs. Groups vs. lokaal).
- Wie de App registration aanmaakt en beheert.
- Non-functionals: session/token-lifetime, logout, conditional access.
- Flutter-integratie als eigen werkstroom.

De story kan prima opgesplitst worden in:

1. *Spike:* opzet App registration + PoC JWT-validatie in Laravel (1–2 dgn).
2. *Backend:* Sanctum eruit, Azure-guard erin, users-tabel migratie.
3. *Frontend:* Flutter OIDC-login integreren.
4. *Afronding:* logout, Conditional Access-tests, docs, oude routes weg.

> Datamigratie is niet nodig: er zijn nog geen productiegebruikers.

---

## 7. Mail-template voor de opdrachtgever

> Beste [NAAM],
>
> Voor het koppelen van onze app aan jullie Microsoft Entra ID willen we
> graag een App registration laten aanmaken in jullie tenant. Jullie
> Azure-beheerder kan dit opzetten via *Microsoft Entra ID >
> App registrations > New registration* met onderstaande instellingen.
>
> **Registratie-instellingen**
>
> - Naam: `[APP_NAAM]`
> - Supported account types: **Single tenant**
> - Platform: **Mobile and desktop applications**
> - Redirect URI: `msauth.[PACKAGE_ID]://auth`
> - Allow public client flows: **Yes**
> - Expose an API → scope toevoegen: `access_as_user`
> - App Roles toevoegen: `Admin` en `Editor`
> - Toegang: iedereen in de tenant mag inloggen (geen assignment required)
>
> Kleine kanttekening: de definitieve `[PACKAGE_ID]` (de Android package
> name / iOS bundle ID van onze Flutter-app) ligt bij ons nog niet vast.
> Redirect URI's zijn in Azure gelukkig ook achteraf nog toe te voegen —
> is het voor jullie oké om de registratie alvast op te zetten en de URI
> later aan te vullen, of willen jullie liever eerst op die waarde wachten?
>
> **Wat wij van jullie terug nodig hebben**
>
> 1. **Tenant ID** (GUID, te vinden onder *Microsoft Entra ID > Overview*).
> 2. **Application (client) ID** (GUID, staat bovenaan de App registration
>    nadat hij is aangemaakt).
> 3. **Full API scope**, in het formaat `api://<client-id>/access_as_user`
>    (te zien onder *Expose an API* nadat de scope is toegevoegd).
>
> Zou je kunnen laten weten of jullie dit kunnen oppakken? Mocht de
> beheerder onderweg vragen hebben, plannen we graag even een korte call
> met diegene in.
>
> Met vriendelijke groet,
> Anthony Ross
