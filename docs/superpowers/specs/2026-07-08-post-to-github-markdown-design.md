# WordPress Plugin: Export Post to GitHub Markdown

Data: 2026-07-08

## Obiettivo

Plugin WordPress che converte i post del blog (singolarmente o in bulk) in
file Markdown e li salva in un repository GitHub privato già esistente.
Lo scopo è costruire un corpus dello stile di scrittura dell'autore da
usare in futuro per addestrare/guidare la generazione di nuovi articoli
in quello stile.

## Scope

- Post type: solo `post` standard di WordPress.
- Stato: solo post con stato `publish`.
- Nessuna gestione immagini binarie: nel Markdown restano solo i link
  assoluti alle immagini (`![alt](url-assoluto-sul-sito)`), niente upload
  di media su GitHub.
- Repository GitHub privato già esistente e creato manualmente
  dall'utente; il plugin non crea repository.

## Componenti

### 1. Settings page
Nuova voce sotto **Impostazioni → Post to GitHub MD**, con i campi:
- GitHub Personal Access Token (salvato come opzione WP; campo password,
  non esposto in REST pubbliche).
- Owner/repo (es. `gioxx/blog-style-corpus`).
- Branch di destinazione (default `main`).
- Cartella base nel repo (default `posts/`).

### 2. Converter (HTML → Markdown)
- Libreria `league/html-to-markdown`, installata via Composer, con
  `vendor/` incluso nel bundle del plugin.
- Applicato al contenuto renderizzato del post (`the_content` già
  processato dai blocchi Gutenberg, prima dell'output HTML finale ma
  dopo l'esecuzione degli shortcode/blocchi).
- Le tag `<img>` vengono preservate come link Markdown con URL assoluto;
  nessun fetch o riscrittura del binario.

### 3. GitHub client
Wrapper minimale sulle REST API "Contents":
- `GET /repos/{owner}/{repo}/contents/{path}` per verificare se il file
  esiste già e recuperarne lo SHA (necessario per l'update).
- `PUT /repos/{owner}/{repo}/contents/{path}` per creare o aggiornare il
  file (con `sha` se update, senza se create), specificando branch e
  commit message.
- Autenticazione via PAT nell'header `Authorization: Bearer {token}`.

### 4. Export service
Per ogni post da esportare:
1. Genera lo slug e il path: `{cartella_base}/{anno_pubblicazione}/{slug}.md`
   (es. `posts/2026/come-configurare-wordpress.md`). L'anno è quello di
   pubblicazione (`post_date`), non di modifica.
2. Costruisce il front matter YAML:
   ```yaml
   ---
   title: "Titolo del post"
   slug: come-configurare-wordpress
   date: 2026-07-08T10:30:00+02:00
   modified: 2026-07-08T11:00:00+02:00
   wp_id: 1234
   categories: ["WordPress", "Tutorial"]
   tags: ["plugin", "github"]
   permalink: https://tuosito.it/come-configurare-wordpress/
   ---
   ```
3. Converte il contenuto in Markdown e lo appende dopo il front matter.
4. Se il post ha già `_gh_md_path` salvato (export precedente), usa
   quel path/SHA per fare un update in place invece di creare un nuovo
   file (anche se lo slug o l'anno fossero cambiati, si aggiorna lo
   stesso file esistente sovrascrivendone il path solo se il path
   calcolato è cambiato — in tal caso si crea il nuovo file e non si
   cancella il vecchio, che resta orfano; questo caso limite non è
   gestito attivamente in questa prima versione).
5. Chiama il GitHub client con commit message:
   `Export post: {title} (#{wp_id})`.
6. In caso di successo, salva su post meta:
   - `_gh_md_path`
   - `_gh_md_sha`
   - `_gh_md_exported_at` (timestamp UTC)
7. In caso di errore (PAT invalido, rate limit, conflitto SHA, errore di
   rete), non aggiorna i meta e restituisce un messaggio d'errore
   descrittivo al chiamante (metabox o pagina bulk).

### 5. Metabox nell'editor del singolo post
- Visibile nella schermata di modifica di un post pubblicato.
- Mostra lo stato corrente: "Mai esportato" / "Esportato il {data}" /
  "Modificato dopo l'ultima esportazione" (confronto tra
  `_gh_md_exported_at` e `post_modified`).
- Pulsante "Esporta su GitHub" che esegue l'export via AJAX
  (admin-ajax con nonce), senza ricaricare la pagina, e aggiorna lo
  stato mostrato al termine.

### 6. Pagina di bulk export
- Nuova pagina in amministrazione (sotto **Strumenti** o voce di menu
  dedicata del plugin).
- Tabella dei post pubblicati con colonne: titolo, data pubblicazione,
  stato export (stessi tre stati della metabox), checkbox di selezione.
- Filtri semplici per categoria e intervallo di date (facoltativi, non
  bloccanti per la v1 se aumentano troppo lo scope — vedi nota sotto).
- Pulsante "Esporta selezionati": esegue gli export in sequenza
  (richieste AJAX una per post, per evitare timeout su bulk grandi) e
  mostra un riepilogo finale con conteggio successi/fallimenti e il
  motivo di ogni fallimento.

## Gestione errori

- PAT mancante o owner/repo non configurati: notice di amministrazione
  bloccante sulle pagine del plugin (metabox e bulk page mostrano un
  avviso e disabilitano il pulsante di export).
- Errore di autenticazione GitHub (401) o repo/branch non trovato (404):
  messaggio d'errore chiaro, nessuna modifica ai post meta.
- Conflitto SHA (409, es. file modificato manualmente su GitHub nel
  frattempo): messaggio d'errore che invita l'utente a ri-esportare.
- Rate limit GitHub (403 con header rate limit): messaggio d'errore con
  indicazione di riprovare più tardi.

## Fuori scope (v1)

- Creazione automatica del repository.
- Gestione/upload di immagini binarie su GitHub.
- Export automatico alla pubblicazione (hook `publish_post`) — l'utente
  ha scelto trigger manuali (metabox + bulk); potrà essere aggiunto in
  una versione successiva.
- Sincronizzazione bidirezionale (GitHub → WordPress).
- Gestione di post type diversi da `post` o stati diversi da `publish`.
- Filtri avanzati nella pagina bulk oltre a categoria/intervallo date
  base.

## Note tecniche

- Requisiti: PHP 7.4+, WordPress 6.0+, Composer per l'installazione
  della dipendenza `league/html-to-markdown` (il `vendor/` viene
  distribuito già incluso nel plugin, l'utente finale non deve eseguire
  Composer).
- Il PAT GitHub deve avere permesso `repo` (o, se fine-grained, accesso
  in scrittura ai `contents` del repository specifico).
