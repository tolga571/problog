import { Crepe } from '@milkdown/crepe';

async function uploadImage(file) {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const fd = new FormData();
  fd.append('file', file);
  const response = await fetch('/actions/upload_image.php', {
    method: 'POST',
    headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'fetch' },
    body: fd,
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(data.message || 'Görsel yüklenemedi.');
  }
  return data.url;
}

export function initMarkdownEditor(containerEl, hiddenTextareaEl, initialMarkdown) {
  if (!containerEl || !hiddenTextareaEl) return;

  const wrapper = document.createElement('div');
  wrapper.className = 'milkdown-editor-shell';
  containerEl.appendChild(wrapper);

  const crepe = new Crepe({
    root: wrapper,
    defaultValue: initialMarkdown || '',
    features: {
      // AI özelliği bir LLM sağlayıcı gerektirir, TopBar ise ayrı bir
      // başlık alanı zaten olduğu için kapatıldı.
      [Crepe.Feature.AI]: false,
      [Crepe.Feature.TopBar]: false,
    },
    featureConfigs: {
      [Crepe.Feature.Placeholder]: {
        text: 'Makaleni yaz... "/" ile blok ekleyebilirsin',
        mode: 'block',
      },
      [Crepe.Feature.ImageBlock]: {
        onUpload: uploadImage,
        blockOnUpload: uploadImage,
        inlineOnUpload: uploadImage,
      },
    },
  });

  crepe.on((listener) => {
    listener.markdownUpdated((_ctx, markdown) => {
      hiddenTextareaEl.value = markdown;
    });
  });

  crepe
    .create()
    .then(() => {
      hiddenTextareaEl.style.display = 'none';
    })
    .catch((err) => {
      console.error('Milkdown yüklenemedi, düz metin moduna dönülüyor.', err);
      wrapper.remove();
    });
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-milkdown-target]').forEach((container) => {
    const textareaId = container.getAttribute('data-milkdown-target');
    const textarea = document.getElementById(textareaId);
    if (!textarea) return;
    initMarkdownEditor(container, textarea, textarea.value);
  });
});
