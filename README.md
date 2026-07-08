# Post to GitHub Markdown

Plugin WordPress che esporta i tuoi articoli pubblicati come file Markdown (con front matter YAML) in un repository GitHub privato già esistente. Lo scopo è costruire nel tempo un corpus dei tuoi articoli in formato testuale, utile per addestrare o guidare uno stile di scrittura coerente in strumenti come Claude.

Il plugin non crea il repository, non carica immagini binarie e lavora solo su articoli (`post`) con stato "pubblicato".

## Requisiti

- WordPress 6.0 o superiore
- PHP 7.4 o superiore
- Un repository GitHub privato già creato
- Un Personal Access Token (PAT) di GitHub con permesso di scrittura sul repository (scope classico `repo`, oppure un fine-grained token con accesso in lettura/scrittura ai "Contents" di quel repository)

## Installazione

1. Copia l'intera cartella del plugin in `wp-content/plugins/post-to-github-md/` sul tuo sito WordPress (la cartella `vendor/` con le dipendenze è già inclusa: non serve eseguire Composer).
2. Vai su **Plugin** nella bacheca di WordPress e attiva "Post to GitHub Markdown".

## Configurazione

Vai su **Impostazioni → Post to GitHub MD** e compila:

| Campo | Descrizione | Esempio |
|---|---|---|
| **GitHub Personal Access Token** | Il PAT con accesso in scrittura al repository di destinazione. Non viene mai mostrato in chiaro altrove nel sito. | `ghp_xxxxxxxxxxxxxxxxxxxx` |
| **Owner/repo** | Proprietario e nome del repository GitHub, nel formato `owner/repo`. Il repository deve già esistere. | `tuonome/il-tuo-repo-privato` |
| **Branch** | Il branch su cui scrivere i file. | `main` |
| **Base folder** | La cartella di primo livello nel repository dove salvare gli export. | `posts` |

Salva le modifiche. Finché PAT e repository non sono configurati, il plugin blocca ogni tentativo di esportazione (sia dal singolo post che dall'export in blocco) mostrando un messaggio d'errore invece di tentare la chiamata a GitHub.

### Dove creare il Personal Access Token

Su GitHub: **Settings → Developer settings → Personal access tokens**. Con un classic token basta lo scope `repo`; con un fine-grained token, assicurati che l'accesso "Contents" sia impostato su "Read and write" per il repository selezionato.

## Come esportare un singolo post

1. Apri in modifica un articolo già pubblicato.
2. Nella barra laterale trovi il box **"Export to GitHub"**, che mostra lo stato corrente:
   - **Mai esportato** — il post non è mai stato inviato al repository.
   - **Esportato il [data]** — l'ultima esportazione riuscita, con data e ora.
   - **Modificato dopo l'ultima esportazione** — il post è stato aggiornato dopo l'ultimo export; conviene ri-esportarlo.
3. Clicca **"Esporta su GitHub"**. L'operazione avviene via AJAX senza ricaricare la pagina; lo stato si aggiorna al termine.

Se il post era già stato esportato in precedenza, il plugin aggiorna lo stesso file su GitHub (stesso percorso, stesso commit history) invece di crearne uno nuovo.

## Come esportare più post insieme (bulk export)

1. Vai su **Strumenti → Export to GitHub MD**.
2. Trovi l'elenco di tutti gli articoli pubblicati, con una colonna di stato identica a quella del box nel singolo post.
3. Seleziona i post che vuoi esportare (c'è anche una checkbox "seleziona tutto") e clicca **"Esporta selezionati"**.
4. Il plugin esporta un post alla volta (per evitare timeout su elenchi lunghi) e aggiorna via via lo stato di ogni riga. Al termine viene mostrato un riepilogo con il numero di post esportati con successo ed eventuali errori con il motivo.

## Dove finiscono i file su GitHub

Ogni post viene salvato come:

```
{base_folder}/{anno_di_pubblicazione}/{slug-del-post}.md
```

Ad esempio, con base folder `posts`, un articolo pubblicato nel 2026 con slug `come-configurare-wordpress` diventa `posts/2026/come-configurare-wordpress.md`. L'anno è quello di **pubblicazione** del post, non dell'ultima modifica.

Ogni file inizia con un front matter YAML seguito dal contenuto convertito in Markdown:

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

# Titolo del post

Contenuto dell'articolo in Markdown...
```

Le immagini presenti nel contenuto restano come link assoluti al tuo sito (`![alt](https://tuosito.it/wp-content/uploads/...)`): non vengono caricate né duplicate su GitHub.

Il messaggio di commit generato per ogni export è nel formato `Export post: {titolo} (#{id})`, così puoi seguire facilmente la cronologia degli export nel repository.

## Risoluzione dei problemi

- **"Configura prima PAT e repository nelle impostazioni del plugin"**: il token o l'owner/repo non sono ancora impostati (o il formato `owner/repo` inserito non è valido). Controlla le impostazioni del plugin.
- **Errore di autenticazione / repository non trovato**: verifica che il PAT sia valido, non scaduto, e abbia i permessi corretti sul repository indicato.
- **Conflitto (409) durante l'export**: significa che il file su GitHub è stato modificato o rinominato direttamente dal repository dopo l'ultima esportazione da WordPress, e il riferimento salvato dal plugin non corrisponde più allo stato reale del file. Controlla il contenuto del repository prima di ri-esportare; se necessario, verifica manualmente il file su GitHub.
- **Limite di rate GitHub**: se esporti molti post in blocco e ricevi un errore di rate limit, attendi qualche minuto e riprova.
- **Un post in bozza non mostra il pulsante di export, o l'export fallisce con "Solo i post pubblicati possono essere esportati"**: per scelta di progetto, solo gli articoli con stato "pubblicato" possono essere esportati.

## Limiti noti (v1)

- Solo il post type `post` è supportato (non pagine o altri tipi di contenuto personalizzati).
- Nessuna sincronizzazione automatica alla pubblicazione: l'export va sempre avviato manualmente (singolo post o bulk).
- Nessuna creazione automatica del repository: deve già esistere prima di configurare il plugin.
- Nessun upload o gestione delle immagini: restano link assoluti al sito di origine.
