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

- [x] I. Concetto "Area" abolito (non un rename) — fatto: bookableRelations()
      ora è hasMany su parent_bookable_resource_id; fillable/docblock reali +
      cast is_required; migration fantasma rimossa dal provider; factory morte
      cancellate. Test che esegue la query.
- [x] J. `ensureIsAvailable` — fatto: trait su BookableResource, tipi
      PeriodCollection fino in fondo, delega "almeno una risorsa" su
      IsBookable. 7 test nuovi sul percorso completo.

## Schema e robustezza

- [x] K. `booked_periods.starts_at/ends_at` NOT NULL — fatto: stub aggiornati +
      migration update `update_booked_periods_constraints`. FK: booking_id →
      restrict, bookable_resource_id → nullOnDelete (storico preservato);
      swap FK saltato su SQLite.
- [x] L. Minori — fatto: messaggio RandomBookingCode (soglia 38), checksum con
      getKey() + JSON_THROW_ON_ERROR, scope disponibilità riscritto come
      subquery del builder (cross-db, niente raw hardcoded), max letto dalla
      riga lockata, afterCommit sulla connessione del booking, indice composito
      plannings (`update_bookable_plannings_add_indexes`).
- [x] M. Qualità test — fatto: asserzioni reali (`not->toThrow`) al posto di
      `expect(true)`, tempo congelato in tutti i file che usano now().
- [x] N. Documentazione — fatto: guida "Upgrading from 1.x" in
      migration-guide.md (cleanup NULL, nuove migration, cambio FK, tabella
      before/after), docs allineate al codice.

## Review

(da compilare a fine lavori)
