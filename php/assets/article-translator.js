import { EditorState } from 'prosemirror-state';
import { EditorView } from 'prosemirror-view';
import { DOMParser as PMDOMParser, DOMSerializer } from 'prosemirror-model';
import { schema } from 'prosemirror-schema-basic';
import { exampleSetup } from 'prosemirror-example-setup';

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function htmlToDoc(html) {
  const div = document.createElement('div');
  div.innerHTML = html && html.trim() !== '' ? html : '<p></p>';
  return PMDOMParser.fromSchema(schema).parse(div);
}

function docToHtml(doc) {
  const fragment = DOMSerializer.fromSchema(schema).serializeFragment(doc.content);
  const wrap = document.createElement('div');
  wrap.appendChild(fragment);
  return wrap.innerHTML;
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}

class ArticleTranslator {
  constructor(root) {
    this.root = root;
    this.articleId = root.dataset.articleId;
    this.sourceLanguage = root.dataset.sourceLanguage;
    this.languages = JSON.parse(root.dataset.languages || '{}');
    this.currentLanguage = this.sourceLanguage;
    this.views = new Map(); // code -> { view, note, wrap }
    this.sourceByCode = new Map();

    this.root.innerHTML = `
      <div class="translator-tabs flex flex-wrap gap-2 mb-4"></div>
      <div class="translator-title-row card p-3 mb-3">
        <p class="translator-title-hint text-muted-2 text-xs mb-1.5 italic hidden"></p>
        <input type="text" class="translator-title w-full bg-transparent border border-border rounded-lg px-3 py-2 text-base font-semibold placeholder:text-muted-2" placeholder="Başlık çevirisi" maxlength="120" />
      </div>
      <div class="translator-toolbar flex items-center justify-between gap-3 mb-3">
        <span class="translator-percent text-sm text-muted"></span>
        <div class="flex items-center gap-2">
          <button type="button" class="btn-outline text-xs px-3 py-1.5" data-action="add-sentence">+ Cümle Ekle</button>
          <button type="button" class="btn-outline text-xs px-3 py-1.5" data-action="copy-ai">AI için Kopyala</button>
          <button type="button" class="btn-outline text-xs px-3 py-1.5" data-action="toggle-import">JSON Yapıştır</button>
          <button type="button" class="btn-primary text-xs px-4 py-1.5" data-action="save">Kaydet</button>
        </div>
      </div>
      <div class="translator-import hidden mb-4">
        <textarea class="w-full bg-surface-3 border border-border rounded-lg p-3 text-sm" rows="6" placeholder='AI dan donen JSON: {"title":"...","sentences":[{"code":"s1","text":"..."}]}'></textarea>
        <button type="button" class="btn-primary text-xs px-4 py-1.5 mt-2" data-action="import-json">İçe Aktar</button>
      </div>
      <div class="translator-rows flex flex-col gap-3"></div>
      <p class="translator-status text-xs text-muted-2 mt-3"></p>
    `;

    this.tabsEl = root.querySelector('.translator-tabs');
    this.titleEl = root.querySelector('.translator-title');
    this.titleHintEl = root.querySelector('.translator-title-hint');
    this.rowsEl = root.querySelector('.translator-rows');
    this.percentEl = root.querySelector('.translator-percent');
    this.statusEl = root.querySelector('.translator-status');
    this.importPanel = root.querySelector('.translator-import');
    this.importTextarea = this.importPanel.querySelector('textarea');

    root.querySelector('[data-action="add-sentence"]').addEventListener('click', () => this.addSentenceRow());
    root.querySelector('[data-action="copy-ai"]').addEventListener('click', () => this.copyForAi());
    root.querySelector('[data-action="toggle-import"]').addEventListener('click', () => this.importPanel.classList.toggle('hidden'));
    root.querySelector('[data-action="import-json"]').addEventListener('click', () => this.importJson());
    root.querySelector('[data-action="save"]').addEventListener('click', () => this.save());

    this.renderTabs();
    this.loadLanguage(this.sourceLanguage);
  }

  renderTabs() {
    this.tabsEl.innerHTML = '';
    Object.entries(this.languages).forEach(([code, name]) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'composer-tab' + (code === this.currentLanguage ? ' active' : '');
      btn.textContent = name + (code === this.sourceLanguage ? ' (Kaynak)' : '');
      btn.addEventListener('click', () => {
        if (code === this.currentLanguage) return;
        this.currentLanguage = code;
        this.renderTabs();
        this.loadLanguage(code);
      });
      this.tabsEl.appendChild(btn);
    });
  }

  async loadLanguage(languageId) {
    this.status('Yükleniyor...');
    const url = `/actions/article_translation.php?article_id=${encodeURIComponent(this.articleId)}&language_id=${encodeURIComponent(languageId)}`;
    const res = await fetch(url, { headers: { 'X-Requested-With': 'fetch' } });
    const data = await res.json().catch(() => null);
    if (!res.ok || !data) {
      this.status('Çeviri verisi yüklenemedi.');
      return;
    }

    this.isSource = data.is_source;
    this.sourceTitle = data.source_title || '';
    this.sourceByCode = new Map(data.source_sentences.map((s) => [s.code, s]));
    this.renderRows(data.sentences);
    this.renderTitle(data.title || '');
    this.updatePercent(data.percent);
    this.status('');
  }

  renderTitle(title) {
    this.titleEl.value = title;
    this.titleEl.readOnly = this.isSource;
    this.titleEl.classList.toggle('opacity-60', this.isSource);
    if (!this.isSource) {
      this.titleHintEl.textContent = this.sourceTitle;
      this.titleHintEl.classList.remove('hidden');
    } else {
      this.titleHintEl.classList.add('hidden');
    }
  }

  renderRows(sentences) {
    this.views.forEach((entry) => entry.view && entry.view.destroy());
    this.views.clear();
    this.rowsEl.innerHTML = '';

    sentences
      .slice()
      .sort((a, b) => a.sort - b.sort)
      .forEach((sentence) => this.addSentenceRow(sentence, false));
  }

  addSentenceRow(sentence, focus = true) {
    const code = sentence?.code || 't' + Math.random().toString(36).slice(2, 9);
    const isNewToLang = !sentence;
    const text = sentence?.text || '';
    const note = sentence?.note || '';

    const row = document.createElement('div');
    row.className = 'translator-row card p-3 flex gap-3';
    row.draggable = !this.isSource;
    row.dataset.code = code;

    const sourcePreview = this.sourceByCode.get(code);

    row.innerHTML = `
      <div class="translator-drag flex flex-col items-center gap-1 text-muted-2 ${this.isSource ? 'invisible' : ''}" title="Sürükle-bırak ile sırala">
        <span class="material-symbols-outlined text-lg">drag_indicator</span>
        <span class="text-[10px]">${escapeHtml(code)}</span>
      </div>
      <div class="flex-1 min-w-0">
        ${!this.isSource && sourcePreview ? `
          <div class="flex items-start justify-between gap-2 mb-1.5">
            <p class="text-muted-2 text-xs italic flex-1">${escapeHtml(sourcePreview.text)}</p>
            <button type="button" class="translator-ai-btn" title="Bu cümleyi AI ile çevir">
              <span class="material-symbols-outlined text-sm">auto_awesome</span>
              AI ile çevir
            </button>
          </div>
          <p class="translator-ai-status hidden"></p>
        ` : ''}
        <div class="translator-editor ${this.isSource ? 'is-readonly' : ''}"></div>
        <input type="text" class="translator-note w-full bg-transparent border-t border-border mt-2 pt-1.5 text-xs text-muted placeholder:text-muted-2" placeholder="Not (çevirmen için ipucu, örn. terim tercihi)" value="${escapeHtml(note)}" />
        <div class="translator-meta">
          <div class="translator-meta-chips"></div>
          <form class="translator-meta-form">
            <input type="text" class="translator-meta-key" placeholder="özellik" maxlength="40" />
            <input type="text" class="translator-meta-val" placeholder="değer" maxlength="200" />
            <button type="submit" class="translator-meta-add" title="Özellik ekle">+</button>
          </form>
        </div>
      </div>
      <button type="button" class="translator-delete text-muted-2 hover:text-red-400 ${this.isSource ? 'invisible' : ''}" title="Cümleyi sil">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    `;

    this.rowsEl.appendChild(row);

    const editorContainer = row.querySelector('.translator-editor');
    let view = null;
    if (this.isSource) {
      editorContainer.innerHTML = `<p class="py-1">${escapeHtml(text)}</p>`;
    } else {
      view = new EditorView(editorContainer, {
        state: EditorState.create({ doc: htmlToDoc(text), plugins: exampleSetup({ schema, menuBar: false }) }),
      });
      if (focus) view.focus();
    }

    row.querySelector('.translator-delete').addEventListener('click', () => {
      const entry = this.views.get(code);
      if (entry?.view) entry.view.destroy();
      this.views.delete(code);
      row.remove();
    });

    const meta = sentence?.meta && typeof sentence.meta === 'object' ? { ...sentence.meta } : {};
    const metaChipsEl = row.querySelector('.translator-meta-chips');
    const metaForm = row.querySelector('.translator-meta-form');
    const metaKeyInput = row.querySelector('.translator-meta-key');
    const metaValInput = row.querySelector('.translator-meta-val');

    const renderMetaChips = () => {
      metaChipsEl.innerHTML = '';
      Object.entries(meta).forEach(([key, value]) => {
        const chip = document.createElement('span');
        chip.className = 'translator-meta-chip';
        chip.innerHTML = `<b>${escapeHtml(key)}:</b> ${escapeHtml(value)} <button type="button" class="translator-meta-chip-remove">&times;</button>`;
        chip.querySelector('.translator-meta-chip-remove').addEventListener('click', () => {
          delete meta[key];
          renderMetaChips();
        });
        metaChipsEl.appendChild(chip);
      });
    };
    renderMetaChips();

    metaForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const key = metaKeyInput.value.trim();
      const value = metaValInput.value.trim();
      if (!key || !value) return;
      meta[key] = value;
      renderMetaChips();
      metaKeyInput.value = '';
      metaValInput.value = '';
      metaKeyInput.focus();
    });

    const aiBtn = row.querySelector('.translator-ai-btn');
    const aiStatusEl = row.querySelector('.translator-ai-status');
    aiBtn?.addEventListener('click', async () => {
      aiBtn.disabled = true;
      aiStatusEl.textContent = 'Çevriliyor...';
      aiStatusEl.classList.remove('hidden');
      try {
        const res = await fetch('/actions/ai_translate_sentence.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken(), 'X-Requested-With': 'fetch' },
          body: JSON.stringify({ sentence: sourcePreview?.text || '', target_language: this.currentLanguage }),
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data?.ok) {
          aiStatusEl.textContent = data?.message || 'AI çevirisi alınamadı, "AI için Kopyala" ile manuel çevirebilirsin.';
          return;
        }
        const entry = this.views.get(code);
        if (entry?.view) {
          const tr = entry.view.state.tr.replaceWith(0, entry.view.state.doc.content.size, htmlToDoc(escapeHtml(data.text || '')).content);
          entry.view.dispatch(tr);
        }
        if (data.note) {
          entry.note.value = data.note;
        }
        aiStatusEl.textContent = 'AI çevirisi eklendi, gözden geçirip Kaydet\'e bas.';
      } catch (e) {
        aiStatusEl.textContent = 'AI çevirisi alınamadı, bağlantı hatası.';
      } finally {
        aiBtn.disabled = false;
      }
    });

    row.addEventListener('dragstart', (e) => {
      row.classList.add('opacity-50');
      e.dataTransfer.setData('text/plain', code);
    });
    row.addEventListener('dragend', () => row.classList.remove('opacity-50'));
    row.addEventListener('dragover', (e) => {
      if (this.isSource) return;
      e.preventDefault();
      const after = this.rowAfter(e.clientY);
      if (after == null) {
        this.rowsEl.appendChild(row.dragging ? row : this.dragged());
      }
    });

    this.views.set(code, { view, note: row.querySelector('.translator-note'), meta, refreshMeta: renderMetaChips, wrap: row });

    this.setupDropZone();
  }

  dragged() {
    return this.rowsEl.querySelector('.opacity-50');
  }

  rowAfter(y) {
    const rows = [...this.rowsEl.querySelectorAll('.translator-row:not(.opacity-50)')];
    return rows.reduce((closest, child) => {
      const box = child.getBoundingClientRect();
      const offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) {
        return { offset, element: child };
      }
      return closest;
    }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
  }

  setupDropZone() {
    if (this.dropZoneReady || this.isSource) return;
    this.dropZoneReady = true;
    this.rowsEl.addEventListener('dragover', (e) => {
      e.preventDefault();
      const dragging = this.dragged();
      if (!dragging) return;
      const after = this.rowAfter(e.clientY);
      if (after == null) {
        this.rowsEl.appendChild(dragging);
      } else {
        this.rowsEl.insertBefore(dragging, after);
      }
    });
  }

  collectSentences() {
    return [...this.rowsEl.querySelectorAll('.translator-row')].map((row, i) => {
      const code = row.dataset.code;
      const entry = this.views.get(code);
      const text = this.isSource
        ? (this.sourceByCode.get(code)?.text ?? '')
        : (entry?.view ? docToHtml(entry.view.state.doc) : '');
      const p = this.sourceByCode.get(code)?.p ?? 0;
      return { code, sort: i + 1, p, text, note: entry?.note?.value?.trim() || '', meta: entry?.meta || {} };
    });
  }

  async save() {
    this.status('Kaydediliyor...');
    const sentences = this.collectSentences();
    const title = this.titleEl.value.trim();
    const res = await fetch('/actions/article_translation.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken(), 'X-Requested-With': 'fetch' },
      body: JSON.stringify({ article_id: this.articleId, language_id: this.currentLanguage, title, sentences }),
    });
    const data = await res.json().catch(() => null);
    if (!res.ok || !data?.ok) {
      this.status(data?.message || 'Kaydedilemedi.');
      return;
    }
    this.updatePercent(data.percent);
    this.status('Kaydedildi.');
  }

  updatePercent(percent) {
    if (this.isSource) {
      this.percentEl.textContent = 'Kaynak dil';
    } else {
      this.percentEl.textContent = `Çeviri: %${percent}`;
    }
  }

  status(msg) {
    this.statusEl.textContent = msg;
  }

  copyForAi() {
    const lines = [...this.sourceByCode.values()]
      .sort((a, b) => a.sort - b.sort)
      .map((s) => {
        const metaStr = s.meta && Object.keys(s.meta).length
          ? ` (özellikler: ${Object.entries(s.meta).map(([k, v]) => `${k}=${v}`).join(', ')})`
          : '';
        return `[${s.code}] ${s.text}` + (s.note ? ` (not: ${s.note})` : '') + metaStr;
      });
    const header = `Aşağıdaki başlığı ve cümleleri ${this.languages[this.currentLanguage] || this.currentLanguage} diline çevir. Her cümlenin [code] etiketini koru ve şu formatta JSON döndür: {"title":"...","sentences":[{"code":"s1","text":"..."}]}\n\n[title] ${this.sourceTitle}\n`;
    const payload = header + lines.join('\n');

    navigator.clipboard?.writeText(payload).then(
      () => this.status('AI için metin panoya kopyalandı.'),
      () => this.status('Kopyalanamadı, metni elle seçip kopyala.')
    );
  }

  importJson() {
    let parsed;
    try {
      parsed = JSON.parse(this.importTextarea.value);
    } catch (e) {
      this.status('Geçersiz JSON.');
      return;
    }

    const sentences = Array.isArray(parsed) ? parsed : parsed?.sentences;
    if (!Array.isArray(sentences)) {
      this.status('JSON bir dizi (array) veya {"title":..,"sentences":[..]} biçiminde olmalı.');
      return;
    }

    if (!Array.isArray(parsed) && !this.isSource && typeof parsed.title === 'string') {
      this.titleEl.value = parsed.title;
    }

    sentences.forEach((item) => {
      if (!item?.code) return;
      const entry = this.views.get(item.code);
      if (entry?.view) {
        const tr = entry.view.state.tr.replaceWith(0, entry.view.state.doc.content.size, htmlToDoc(escapeHtml(item.text || '')).content);
        entry.view.dispatch(tr);
        if (item.note) entry.note.value = item.note;
        if (item.meta && typeof item.meta === 'object') {
          Object.assign(entry.meta, item.meta);
          entry.refreshMeta?.();
        }
      } else {
        this.addSentenceRow({ code: item.code, sort: this.views.size + 1, p: 0, text: item.text || '', note: item.note || '', meta: item.meta || {} }, false);
      }
    });
    this.status('İçe aktarıldı, gözden geçirip Kaydet\'e bas.');
    this.importPanel.classList.add('hidden');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-article-translator]').forEach((root) => new ArticleTranslator(root));
});
