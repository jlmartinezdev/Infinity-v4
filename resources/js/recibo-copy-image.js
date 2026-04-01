import html2canvas from 'html2canvas';

/**
 * No incluir en la captura la fila "Descargar PDF" (visible en pantalla, oculta al imprimir).
 */
function ignoreElements(el) {
  if (!el || el.nodeType !== 1) return false;
  if (el.classList && el.classList.contains('print:hidden')) return true;
  return false;
}

/**
 * En el documento clonado, anula modo oscuro / grises de Tailwind para que la
 * captura sea papel blanco y texto negro (como impresión térmica).
 */
function aplicarEstilosCapturaClara(clonedDoc) {
  const style = clonedDoc.createElement('style');
  style.setAttribute('data-recibo-captura', '1');
  style.textContent = `
    #recibo-contenido,
    #recibo-contenido .recibo-termico,
    [id^="recibo-captura-"] {
      background-color: #ffffff !important;
      background: #ffffff !important;
    }
    .recibo-termico {
      border-color: #e5e7eb !important;
      box-shadow: none !important;
    }
    .recibo-termico * {
      color: #111827 !important;
      -webkit-text-fill-color: #111827 !important;
    }
    .recibo-termico svg {
      stroke: #111827 !important;
      color: #111827 !important;
    }
    .recibo-linea {
      border-top-color: #9ca3af !important;
    }
    .recibo-termico [class*="border-l"] {
      border-left-color: #d1d5db !important;
    }
    .recibo-termico img {
      background-color: #ffffff !important;
    }
  `;
  const parent = clonedDoc.head || clonedDoc.documentElement;
  parent.appendChild(style);
}

async function copyElementAsImage(el, button) {
  const originalHtml = button ? button.innerHTML : '';
  try {
    if (button) {
      button.disabled = true;
      button.innerHTML =
        '<span class="inline-flex items-center gap-1.5"><svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Copiando…</span>';
    }

    const canvas = await html2canvas(el, {
      scale: Math.min(2, window.devicePixelRatio || 2),
      useCORS: true,
      allowTaint: false,
      backgroundColor: '#ffffff',
      logging: false,
      ignoreElements,
      onclone(clonedDoc) {
        aplicarEstilosCapturaClara(clonedDoc);
      },
    });

    const blob = await new Promise((resolve, reject) => {
      canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('toBlob'))), 'image/png');
    });

    if (navigator.clipboard && window.ClipboardItem) {
      try {
        await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
        if (button) {
          button.innerHTML =
            '<span class="inline-flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Copiado</span>';
          setTimeout(() => {
            button.innerHTML = originalHtml;
            button.disabled = false;
          }, 2200);
        }
        return;
      } catch (clipErr) {
        console.warn('Clipboard image:', clipErr);
      }
    }

    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'recibo.png';
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    if (button) {
      button.innerHTML =
        '<span class="inline-flex items-center gap-1.5">Descargado (portapapeles no disponible)</span>';
      setTimeout(() => {
        button.innerHTML = originalHtml;
        button.disabled = false;
      }, 3500);
    } else {
      alert('No se pudo copiar al portapapeles; se descargó recibo.png.');
    }
  } catch (e) {
    console.error(e);
    alert(
      'No se pudo generar la imagen del recibo. Probá de nuevo o usá "Imprimir recibo".'
    );
    if (button) {
      button.innerHTML = originalHtml;
      button.disabled = false;
    }
  }
}

function init() {
  document.querySelectorAll('[data-copy-recibo-image]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const sel = btn.getAttribute('data-target');
      const el = sel ? document.querySelector(sel) : document.getElementById('recibo-contenido');
      if (!el) return;
      copyElementAsImage(el, btn);
    });
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
