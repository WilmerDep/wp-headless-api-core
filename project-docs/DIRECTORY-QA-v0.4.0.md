# Directory v0.4.0 — QA checkpoint

This file records the real-CMS validation already completed for the Directory module before handing the contract to a Consumer.

## Real CMS PASS

- Guided person editor.
- Portrait selection and replacement.
- Multi-group assignment.
- CSV validation/preview.
- CSV batch import.
- Three real people imported successfully.
- Remote `image_url` sideloaded into WordPress Media.
- Public image URLs resolved from `cms.hosgedopol.gob.do/wp-content/uploads/...`.
- `/wp-json/headless-core/v1/directory`.
- `/wp-json/headless-core/v1/directory?group=<slug>`.
- `/wp-json/headless-core/v1/directory/groups`.
- Global person drag-and-drop ordering.
- Per-group person drag-and-drop ordering.
- Group drag-and-drop ordering and persistence in `/directory/groups`.

## Import format decision

CSV is the officially supported bulk-import format for v0.4.0.

The admin surface exposes only `.csv`, provides a downloadable UTF-8 CSV template, and rejects non-CSV files before the existing importer executes. XLSX is deferred to a later release and is not part of the v0.4.0 acceptance gate.

## Automated lifecycle/revalidation PASS

The isolated Directory lifecycle suite covers:

- draft -> publish;
- future -> publish when WordPress performs the real transition;
- publish -> draft/private/trash/future;
- republication;
- published content saves;
- global order changes;
- public Directory meta changes;
- group assignment changes;
- group create/edit/delete collection invalidation;
- bulk import/reorder aggregation to one collection invalidation;
- permanent deletion;
- draft-only save suppression;
- Consumer/network failure remaining non-blocking.

Latest checkpoint at the time of this document:

- branch: `feature/directory-v0.4.0`
- HEAD: `f988e1dfd827ff62aba47e4d93c203e0297c3d2f`
- CI: Validate and Build #257 — PASS

## Remaining real-CMS gate

Before the Provider contract is handed to Next.js, run a short real-CMS lifecycle smoke with the configured signed webhook:

1. published person edit;
2. publish -> draft;
3. draft -> publish;
4. publish -> private;
5. publish -> trash;
6. group assignment edit;
7. global reorder;
8. per-group reorder;
9. group reorder;
10. permanent delete on a disposable QA person;
11. verify a simulated Consumer failure does not block WordPress saving.

The Provider endpoints must remain public-read-only and fresh throughout.
