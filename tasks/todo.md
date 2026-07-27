# Audit fix backlog (2026-07-27)

Piano condiviso per risolvere i finding dell'analisi critica. Ogni punto va
approvato insieme prima dell'implementazione. Spuntare solo dopo verifica
(test verdi + review).

## Fix piccoli e a basso rischio

- [x] A. Transazioni sicure — fatto: `\Throwable`, rollback prima degli eventi,
      intento create/update catturato prima del callback (BookingFailed corretto
      su create fallita dopo save). 3 test nuovi, suite 205 verde, PHPStan ok.
- [x] B. `BookResource::update()` — fatto: semantica "null = mantieni" per
      label/note/meta, coerente con code/booker/parent. Test dedicato.
- [x] C. Morph map — fatto: `getMorphClass()` nei 3 siti (create, update,
      relatable). 2 test nuovi con morph map attiva.
- [x] D. `bootIsBookable` — verificato: NON è un bug. `morphOne()->delete()`
      esegue una DELETE senza LIMIT vincolata alle colonne morph e rimuove
      tutte le righe; il test esistente con 2 risorse lo dimostra. Finding
      ritirato, nessuna modifica.

## Decisioni di dominio (design da concordare)

- [x] E. Semantica `max = NULL` — fatto: NULL = illimitato ovunque; scope SQL
      con `max IS NULL OR ...`.
- [x] F. Confini dei periodi — fatto: inclusivi (`<=/>=`) ovunque, scope
      allineati al check (back-to-back = overlap).
- [x] G. `is_excluded` — fatto: escluso dal conteggio occupazione in check e
      scope. Corretto anche il default random `is_excluded` nella
      BookedPeriodFactory (flakiness).
- [x] H. Conteggio per istante — fatto: massima concorrenza di booking distinti
      per istante, calcolata in PHP sotto lock. 9 test nuovi, suite 217 verde
      (x2), PHPStan ok.

## Feature rotte (richiedono scelta: riparare o rimuovere)

- [ ] I. Rename `BookableArea` incompiuto — `bookableRelations()`, fillable di
      `BookableRelation`, migration fantasma nel provider, factory morte.
- [ ] J. Percorso `ensureIsAvailable`/`ensureHasFreeSize` — trait montato sul
      modello sbagliato + TypeError Collection/PeriodCollection; zero test.

## Schema e robustezza

- [ ] K. `booked_periods.starts_at/ends_at` NOT NULL (nuova migration di update
      per gli utenti esistenti).
- [ ] L. Minori: messaggio errore RandomBookingCode, `Bookings::checksum`
      (`getKey()` + json_encode), tabelle hardcoded, indice composito su
      `bookable_plannings`, `cascadeOnDelete` vs SoftDeletes su booked_periods.
- [ ] M. Qualità test: sostituire `expect(true)->toBeTrue()`, introdurre
      `testTime()`, test che eseguano davvero le query delle relazioni.

## Review

(da compilare a fine lavori)
