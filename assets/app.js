(function () {
    function qs(selector, root) {
        return (root || document).querySelector(selector);
    }

    function qsa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function escapeHtml(value) {
        return String(value === null || typeof value === 'undefined' ? '' : value).replace(/[&<>"']/g, function (char) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[char];
        });
    }

    function defaultOptions(type) {
        var map = {
            heading: {text: 'Email headline', level: 1, color: '#ffffff', background: 'transparent', align: 'left', size: 42, weight: 700, pad_v: 18, pad_h: 0, min_height: 0},
            text: {text: 'Write your message here.', color: '#dbeafe', background: 'transparent', align: 'left', size: 16, line_height: 1.55, pad_v: 12, pad_h: 0, min_height: 0},
            image: {src: '', alt: '', width: 600, height: 0, fit: 'contain', align: 'center', pad_v: 12, radius: 0},
            button: {text: 'Learn More', url: 'https://example.com', background: '#2563eb', color: '#ffffff', align: 'center', width: 'auto', radius: 4, pad_v: 14, pad_h: 22, block_pad_v: 12, min_height: 0},
            divider: {color: '#334155', height: 1, width: 100, pad_v: 18},
            spacer: {height: 24},
        };
        return JSON.parse(JSON.stringify(map[type] || {}));
    }

    function option(options, key, fallback) {
        return Object.prototype.hasOwnProperty.call(options || {}, key) ? options[key] : fallback;
    }

    function intOption(options, key, fallback, min, max) {
        var value = parseInt(option(options, key, fallback), 10);
        if (Number.isNaN(value)) {
            value = fallback;
        }
        if (typeof min === 'number') {
            value = Math.max(min, value);
        }
        if (typeof max === 'number') {
            value = Math.min(max, value);
        }
        return value;
    }

    function floatOption(options, key, fallback, min, max) {
        var value = parseFloat(option(options, key, fallback));
        if (Number.isNaN(value)) {
            value = fallback;
        }
        if (typeof min === 'number') {
            value = Math.max(min, value);
        }
        if (typeof max === 'number') {
            value = Math.min(max, value);
        }
        return value;
    }

    function alignValue(value) {
        return ['left', 'center', 'right'].indexOf(value) >= 0 ? value : 'left';
    }

    function colorValue(value, fallback) {
        value = String(value || '').trim();
        return value || fallback;
    }

    function backgroundStyle(value) {
        value = String(value || '').trim();
        return value && value !== 'transparent' ? 'background:' + escapeHtml(value) + ';' : '';
    }

    function minHeightStyle(options) {
        var height = intOption(options, 'min_height', 0, 0, 1200);
        return height > 0 ? 'min-height:' + height + 'px;display:flex;flex-direction:column;justify-content:center;' : '';
    }

    function inlineAttrs(key, editor) {
        return editor ? ' contenteditable="true" spellcheck="true" data-inline-option="' + key + '"' : '';
    }

    function renderEmailBlock(type, options, editor) {
        options = options || {};
        if (type === 'heading') {
            var level = intOption(options, 'level', 1, 1, 3);
            var tag = 'h' + level;
            return '<div style="text-align:' + alignValue(options.align) + ';padding:' + intOption(options, 'pad_v', 18, 0, 120) + 'px ' + intOption(options, 'pad_h', 0, 0, 120) + 'px;' + backgroundStyle(options.background) + minHeightStyle(options) + '"><' + tag + inlineAttrs('text', editor) + ' style="margin:0;font-family:Arial,sans-serif;font-size:' + intOption(options, 'size', 42, 12, 72) + 'px;line-height:1.18;font-weight:' + intOption(options, 'weight', 700, 300, 900) + ';color:' + escapeHtml(colorValue(options.color, '#ffffff')) + '">' + escapeHtml(options.text || 'Email headline') + '</' + tag + '></div>';
        }
        if (type === 'text') {
            return '<div style="text-align:' + alignValue(options.align) + ';padding:' + intOption(options, 'pad_v', 12, 0, 120) + 'px ' + intOption(options, 'pad_h', 0, 0, 120) + 'px;' + backgroundStyle(options.background) + minHeightStyle(options) + '"><p' + inlineAttrs('text', editor) + ' style="margin:0;font-family:Arial,sans-serif;font-size:' + intOption(options, 'size', 16, 10, 32) + 'px;line-height:' + floatOption(options, 'line_height', 1.55, 1, 2.4) + ';color:' + escapeHtml(colorValue(options.color, '#dbeafe')) + '">' + escapeHtml(options.text || '').replace(/\n/g, '<br>') + '</p></div>';
        }
        if (type === 'image') {
            var margin = options.align === 'left' ? '0 auto 0 0' : (options.align === 'right' ? '0 0 0 auto' : '0 auto');
            var imageHeight = intOption(options, 'height', 0, 0, 900);
            var imageHeightStyle = imageHeight > 0 ? 'height:' + imageHeight + 'px;object-fit:' + (options.fit === 'cover' ? 'cover' : 'contain') + ';' : 'height:auto;';
            return '<div style="padding:' + intOption(options, 'pad_v', 12, 0, 120) + 'px 0;text-align:' + alignValue(options.align || 'center') + '">' + (options.src ? '<img src="' + escapeHtml(options.src) + '" alt="' + escapeHtml(options.alt || '') + '" width="' + intOption(options, 'width', 600, 80, 700) + '" style="display:block;max-width:100%;' + imageHeightStyle + 'margin:' + margin + ';border-radius:' + intOption(options, 'radius', 0, 0, 40) + 'px;border:0;">' : '<div class="apcampaign-empty">Image URL needed</div>') + '</div>';
        }
        if (type === 'button') {
            var width = options.width === 'full' ? 'display:block;text-align:center;width:100%;box-sizing:border-box;' : 'display:inline-block;';
            return '<div style="text-align:' + alignValue(options.align || 'center') + ';padding:' + intOption(options, 'block_pad_v', 12, 0, 120) + 'px 0;' + minHeightStyle(options) + '"><a href="' + escapeHtml(options.url || '#') + '" style="' + width + 'background:' + escapeHtml(colorValue(options.background, '#2563eb')) + ';color:' + escapeHtml(colorValue(options.color, '#fff')) + ';padding:' + intOption(options, 'pad_v', 14, 6, 48) + 'px ' + intOption(options, 'pad_h', 22, 10, 90) + 'px;border-radius:' + intOption(options, 'radius', 4, 0, 40) + 'px;text-decoration:none;font-family:Arial,sans-serif;font-weight:700"><span' + inlineAttrs('text', editor) + '>' + escapeHtml(options.text || 'Learn More') + '</span></a></div>';
        }
        if (type === 'divider') {
            return '<div style="padding:' + intOption(options, 'pad_v', 18, 0, 80) + 'px 0;"><hr style="border:0;border-top:' + intOption(options, 'height', 1, 1, 12) + 'px solid ' + escapeHtml(colorValue(options.color, '#334155')) + ';width:' + intOption(options, 'width', 100, 10, 100) + '%;margin:0 auto;"></div>';
        }
        if (type === 'spacer') {
            return '<div style="height:' + parseInt(options.height || 24, 10) + 'px"></div>';
        }
        return '';
    }

    function controlInput(label, key, value, placeholder, type) {
        return '<label>' + label + '<input data-option="' + key + '" type="' + (type || 'text') + '" value="' + escapeHtml(value) + '" placeholder="' + escapeHtml(placeholder || '') + '"></label>';
    }

    function controlTextarea(label, key, value, rows) {
        return '<label>' + label + '<textarea data-option="' + key + '" rows="' + (rows || 4) + '">' + escapeHtml(value) + '</textarea></label>';
    }

    function controlColor(label, key, value) {
        return '<label>' + label + '<span class="apcampaign-control-row"><input data-option="' + key + '" type="color" value="' + escapeHtml(colorValue(value, '#ffffff')) + '"><input data-option="' + key + '" type="text" value="' + escapeHtml(colorValue(value, '#ffffff')) + '"></span></label>';
    }

    function controlRange(label, key, value, min, max, unit, step) {
        return '<label>' + label + '<span class="apcampaign-range-row"><input data-option="' + key + '" type="range" min="' + min + '" max="' + max + '" step="' + (step || 1) + '" value="' + escapeHtml(value) + '"><output>' + escapeHtml(value) + escapeHtml(unit || '') + '</output></span></label>';
    }

    function controlCheckbox(label, key, checked, help) {
        return '<label class="apcampaign-check-row"><span><input data-option="' + key + '" type="checkbox" value="1"' + (checked ? ' checked' : '') + '> ' + escapeHtml(label) + '</span>' + (help ? '<small>' + escapeHtml(help) + '</small>' : '') + '</label>';
    }

    function controlSelect(label, key, value, choices) {
        var html = '<label>' + label + '<select data-option="' + key + '">';
        choices.forEach(function (choice) {
            html += '<option value="' + escapeHtml(choice.value) + '"' + (String(choice.value) === String(value) ? ' selected' : '') + '>' + escapeHtml(choice.label) + '</option>';
        });
        return html + '</select></label>';
    }

    function controlSection(label) {
        return '<div class="apcampaign-control-section">' + escapeHtml(label) + '</div>';
    }

    function fieldFor(type, options) {
        if (type === 'heading') {
            return controlSection('Content') +
                controlInput('Heading Text', 'text', option(options, 'text', ''), 'Heading') +
                controlSelect('Level', 'level', option(options, 'level', 1), [{value: 1, label: 'H1'}, {value: 2, label: 'H2'}, {value: 3, label: 'H3'}]) +
                controlSection('Style') +
                controlRange('Font Size', 'size', intOption(options, 'size', 42, 12, 72), 12, 72, 'px') +
                controlRange('Weight', 'weight', intOption(options, 'weight', 700, 300, 900), 300, 900, '', 100) +
                controlColor('Text Color', 'color', option(options, 'color', '#ffffff')) +
                controlInput('Background', 'background', option(options, 'background', 'transparent'), 'transparent or #000000') +
                controlSelect('Alignment', 'align', option(options, 'align', 'left'), [{value: 'left', label: 'Left'}, {value: 'center', label: 'Center'}, {value: 'right', label: 'Right'}]) +
                controlSection('Spacing') +
                controlRange('Vertical Padding', 'pad_v', intOption(options, 'pad_v', 18, 0, 120), 0, 120, 'px') +
                controlRange('Horizontal Padding', 'pad_h', intOption(options, 'pad_h', 0, 0, 120), 0, 120, 'px') +
                controlRange('Minimum Height', 'min_height', intOption(options, 'min_height', 0, 0, 900), 0, 900, 'px');
        }
        if (type === 'text') {
            return controlSection('Content') +
                controlTextarea('Text', 'text', option(options, 'text', ''), 6) +
                controlSection('Style') +
                controlRange('Font Size', 'size', intOption(options, 'size', 16, 10, 32), 10, 32, 'px') +
                controlRange('Line Height', 'line_height', floatOption(options, 'line_height', 1.55, 1, 2.4), 1, 2.4, '', 0.05) +
                controlColor('Text Color', 'color', option(options, 'color', '#dbeafe')) +
                controlInput('Background', 'background', option(options, 'background', 'transparent'), 'transparent or #000000') +
                controlSelect('Alignment', 'align', option(options, 'align', 'left'), [{value: 'left', label: 'Left'}, {value: 'center', label: 'Center'}, {value: 'right', label: 'Right'}]) +
                controlSection('Spacing') +
                controlRange('Vertical Padding', 'pad_v', intOption(options, 'pad_v', 12, 0, 120), 0, 120, 'px') +
                controlRange('Horizontal Padding', 'pad_h', intOption(options, 'pad_h', 0, 0, 120), 0, 120, 'px') +
                controlRange('Minimum Height', 'min_height', intOption(options, 'min_height', 0, 0, 900), 0, 900, 'px');
        }
        if (type === 'image') {
            return controlSection('Content') +
                controlInput('Image URL', 'src', option(options, 'src', ''), 'https:// image URL', 'url') +
                controlInput('Alt Text', 'alt', option(options, 'alt', ''), 'Alt text') +
                controlSection('Layout') +
                controlRange('Width', 'width', intOption(options, 'width', 600, 80, 700), 80, 700, 'px') +
                controlRange('Height', 'height', intOption(options, 'height', 0, 0, 900), 0, 900, 'px') +
                controlSelect('Fit', 'fit', option(options, 'fit', 'contain'), [{value: 'contain', label: 'Contain'}, {value: 'cover', label: 'Cover'}]) +
                controlSelect('Alignment', 'align', option(options, 'align', 'center'), [{value: 'left', label: 'Left'}, {value: 'center', label: 'Center'}, {value: 'right', label: 'Right'}]) +
                controlRange('Corner Radius', 'radius', intOption(options, 'radius', 0, 0, 40), 0, 40, 'px') +
                controlRange('Vertical Padding', 'pad_v', intOption(options, 'pad_v', 12, 0, 120), 0, 120, 'px');
        }
        if (type === 'button') {
            return controlSection('Content') +
                controlInput('Button Text', 'text', option(options, 'text', ''), 'Button text') +
                controlInput('Button URL', 'url', option(options, 'url', ''), 'https://', 'url') +
                controlSection('Style') +
                controlColor('Button Color', 'background', option(options, 'background', '#2563eb')) +
                controlColor('Text Color', 'color', option(options, 'color', '#ffffff')) +
                controlSelect('Alignment', 'align', option(options, 'align', 'center'), [{value: 'left', label: 'Left'}, {value: 'center', label: 'Center'}, {value: 'right', label: 'Right'}]) +
                controlSelect('Width', 'width', option(options, 'width', 'auto'), [{value: 'auto', label: 'Auto'}, {value: 'full', label: 'Full Width'}]) +
                controlRange('Corner Radius', 'radius', intOption(options, 'radius', 4, 0, 40), 0, 40, 'px') +
                controlRange('Button Vertical Padding', 'pad_v', intOption(options, 'pad_v', 14, 6, 48), 6, 48, 'px') +
                controlRange('Button Horizontal Padding', 'pad_h', intOption(options, 'pad_h', 22, 10, 90), 10, 90, 'px') +
                controlRange('Block Vertical Padding', 'block_pad_v', intOption(options, 'block_pad_v', 12, 0, 120), 0, 120, 'px') +
                controlRange('Minimum Height', 'min_height', intOption(options, 'min_height', 0, 0, 900), 0, 900, 'px');
        }
        if (type === 'divider') {
            return controlSection('Style') +
                controlColor('Line Color', 'color', option(options, 'color', '#334155')) +
                controlRange('Thickness', 'height', intOption(options, 'height', 1, 1, 12), 1, 12, 'px') +
                controlRange('Width', 'width', intOption(options, 'width', 100, 10, 100), 10, 100, '%') +
                controlRange('Vertical Padding', 'pad_v', intOption(options, 'pad_v', 18, 0, 80), 0, 80, 'px');
        }
        if (type === 'spacer') {
            return controlRange('Height', 'height', intOption(options, 'height', 24, 8, 160), 8, 160, 'px');
        }
        return '';
    }

    function initComposer(form) {
        var canvas = qs('[data-apcampaign-canvas]', form);
        var raw = qs('[data-apcampaign-raw]', form);
        var designField = qs('[name="design_json"]', form);
        var renderedField = qs('[name="rendered_html"]', form);
        var modeField = qs('[name="composer_mode"]', form);
        var resultBox = qs('[data-apcampaign-results]', form);
        var previewPanel = qs('[data-apcampaign-preview-panel]', form);
        var rawCharCount = qs('[data-apcampaign-char-count]', form);
        var design = {blocks: []};
        var selectedIndex = -1;
        var draggedIndex = null;
        var draggedType = '';
        var dropIndicator = null;
        var pointerDrag = null;
        var history = [];
        var future = [];
        var restoring = false;
        var dirty = false;
        var templatesByKey = {};
        var inspectorEditActive = false;
        var inlineEditActive = false;
        var toolbarCommandHandled = false;
        var grapesEditor = null;
        var grapesHost = null;
        var useGrapes = false;
        var templateModal = null;

        function cloneDesign(value) {
            return JSON.parse(JSON.stringify(value || {blocks: []}));
        }

        function updateCommandState() {
            form.classList.toggle('has-unsaved-changes', dirty);
        }

        function recordHistory() {
            if (restoring) {
                return;
            }
            history.push(cloneDesign(design));
            if (history.length > 60) {
                history.shift();
            }
            future = [];
            dirty = true;
            updateCommandState();
        }

        function restoreDesign(nextDesign) {
            restoring = true;
            design = cloneDesign(nextDesign);
            selectedIndex = -1;
            sync();
            paint();
            restoring = false;
            dirty = true;
            updateCommandState();
        }

        function undo() {
            if (!history.length) {
                return;
            }
            future.push(cloneDesign(design));
            restoreDesign(history.pop());
        }

        function redo() {
            if (!future.length) {
                return;
            }
            history.push(cloneDesign(design));
            restoreDesign(future.pop());
        }

        try {
            design = JSON.parse(designField.value || '{"blocks":[]}');
        } catch (error) {
            design = {blocks: []};
        }
        useGrapes = modeField.value === 'block' && !!window.grapesjs && (design.editor === 'grapesjs' || ((parseInt(form.getAttribute('data-campaign-id') || '0', 10) < 1) && (!Array.isArray(design.blocks) || !design.blocks.length)));
        if (!design.blocks && !useGrapes) {
            design.blocks = [];
        }

        function ensureBlockIds() {
            if (!Array.isArray(design.blocks)) {
                design.blocks = [];
            }
            design.blocks.forEach(function (block) {
                if (!block.id) {
                    block.id = String(Date.now()) + '-' + Math.random().toString(16).slice(2);
                }
                if (!block.options) {
                    block.options = {};
                }
            });
        }

        function sync() {
            ensureBlockIds();
            designField.value = JSON.stringify(design);
        }

        function emailDocument(html, css) {
            var responsiveCss = '@media(max-width:520px){body{padding:12px!important;}body>div{max-width:100%!important;}table[data-apcampaign-teaser],table[data-apcampaign-teaser] tbody,table[data-apcampaign-teaser] tr,table[data-apcampaign-teaser] td{display:block!important;width:100%!important;box-sizing:border-box!important;}table[data-apcampaign-teaser] td{padding-right:0!important;padding-bottom:12px!important;}table[data-apcampaign-teaser] img[data-apcampaign-teaser-image]{width:100%!important;max-width:100%!important;height:auto!important;}}';
            return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>' + responsiveCss + (css || '') + '</style></head><body style="margin:0;background:#f1f5f9;padding:28px;"><div style="max-width:680px;margin:0 auto;background:#ffffff;color:#111827;">' + (html || '') + '</div></body></html>';
        }

        function cleanEmailHtml(html) {
            if (!html || !window.DOMParser) {
                return html || '';
            }
            try {
                var doc = new DOMParser().parseFromString('<div>' + html + '</div>', 'text/html');
                qsa('[data-apcampaign-media-button]', doc).forEach(function (node) {
                    node.parentNode.removeChild(node);
                });
                return doc.body.firstChild ? doc.body.firstChild.innerHTML : html;
            } catch (error) {
                return html;
            }
        }

        function previewSuiteDocument(emailHtml) {
            var encoded = escapeHtml(emailHtml);
            return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>apCampaign Preview</title><style>body{margin:0;background:#0d1b2a;color:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.preview-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 18px;border-bottom:1px solid rgba(255,255,255,.1);background:#06111f}.preview-head h1{margin:0;font-size:18px}.preview-grid{display:grid;grid-template-columns:minmax(0,1fr) 390px 230px;gap:18px;padding:18px;align-items:start}.preview-device{display:grid;gap:8px}.preview-device strong{font-size:12px;text-transform:uppercase;color:#cbd5e1}.preview-frame{width:100%;height:760px;border:0;border-radius:8px;background:#fff;box-shadow:0 12px 36px rgba(0,0,0,.24)}.preview-tablet .preview-frame{height:720px}.preview-mobile .preview-frame{height:720px}@media(max-width:1100px){.preview-grid{grid-template-columns:1fr}.preview-tablet,.preview-mobile{max-width:none}.preview-frame{height:680px}}</style></head><body><header class="preview-head"><h1>apCampaign Preview</h1><span>Desktop, tablet, mobile</span></header><main class="preview-grid"><section class="preview-device preview-desktop"><strong>Desktop</strong><iframe sandbox="" class="preview-frame" srcdoc="' + encoded + '"></iframe></section><section class="preview-device preview-tablet"><strong>Tablet</strong><iframe sandbox="" class="preview-frame" srcdoc="' + encoded + '"></iframe></section><section class="preview-device preview-mobile"><strong>Mobile</strong><iframe sandbox="" class="preview-frame" srcdoc="' + encoded + '"></iframe></section></main></body></html>';
        }

        function previewTabbedDocument(emailHtml) {
            var encoded = escapeHtml(emailHtml);
            return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>apCampaign Preview</title><style>body{margin:0;background:#0d1b2a;color:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.preview-wrap{width:min(850px,100%);margin:0 auto}.preview-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 14px;border-bottom:1px solid rgba(255,255,255,.1);background:#06111f}.preview-head h1{margin:0;font-size:16px}.preview-tabs{display:flex;gap:7px}.preview-tabs button{min-height:32px;padding:6px 10px;color:#dbeafe;background:#142842;border:1px solid rgba(255,255,255,.14);border-radius:6px;cursor:pointer;font-weight:700}.preview-tabs button.active{background:#ff6a00;border-color:#ff6a00;color:#06111f}.preview-stage{display:grid;place-items:start center;height:calc(100vh - 59px);padding:34px 18px 18px;overflow:auto}.preview-shell{width:min(680px,100%);max-width:100%;transition:width .18s ease}.preview-shell.is-tablet{width:min(720px,100%)}.preview-shell.is-mobile{width:min(390px,100%)}.preview-device-label{margin:0 0 12px;color:#f8fafc;font-size:13px;font-weight:800;letter-spacing:.08em;text-align:center;text-transform:uppercase}.preview-frame{display:block;width:100%;height:620px;border:0;border-radius:8px;background:#fff;box-shadow:0 12px 34px rgba(0,0,0,.26)}@media(max-height:760px){.preview-frame{height:540px}.preview-stage{padding-top:24px}}</style></head><body><div class="preview-wrap"><header class="preview-head"><h1>apCampaign Preview</h1><nav class="preview-tabs" aria-label="Preview size"><button type="button" class="active" data-size="desktop">Desktop</button><button type="button" data-size="tablet">Tablet</button><button type="button" data-size="mobile">Mobile</button></nav></header><main class="preview-stage"><div class="preview-shell" data-preview-shell><p class="preview-device-label" data-preview-label>Desktop</p><iframe sandbox="" class="preview-frame" srcdoc="' + encoded + '"></iframe></div></main></div><script>document.querySelectorAll("[data-size]").forEach(function(button){button.addEventListener("click",function(){var size=button.getAttribute("data-size");var label=size.charAt(0).toUpperCase()+size.slice(1);var width=size==="tablet"?"720px":size==="mobile"?"390px":"680px";document.querySelectorAll("[data-size]").forEach(function(item){item.classList.toggle("active",item===button);});document.querySelector("[data-preview-label]").textContent=label;var shell=document.querySelector("[data-preview-shell]");shell.className="preview-shell"+(size==="tablet"?" is-tablet":size==="mobile"?" is-mobile":"");shell.style.width=width;});});</script></body></html>';
        }

        function serializable(value) {
            if (!value) {
                return value;
            }
            if (typeof value.toJSON === 'function') {
                return value.toJSON();
            }
            return JSON.parse(JSON.stringify(value));
        }

        function collectGrapesDesign() {
            if (!grapesEditor) {
                return design && design.editor === 'grapesjs' ? design : {editor: 'grapesjs', version: 1, components: [], styles: [], html: '', css: '', assets: []};
            }
            var html = grapesEditor.getHtml() || '';
            var css = grapesEditor.getCss() || '';
            var assets = [];
            try {
                assets = grapesEditor.AssetManager.getAll().map(function (asset) {
                    return serializable(asset);
                });
            } catch (error) {
                assets = [];
            }
            return {
                editor: 'grapesjs',
                version: 1,
                components: serializable(grapesEditor.getComponents()),
                styles: serializable(grapesEditor.getStyle()),
                html: html,
                css: css,
                assets: assets
            };
        }

        function syncGrapes() {
            if (!useGrapes) {
                return;
            }
            design = collectGrapesDesign();
            designField.value = JSON.stringify(design);
            if (renderedField) {
                renderedField.value = emailDocument(cleanEmailHtml(design.html || ''), design.css || '');
            }
        }

        function loadGrapesDesign(nextDesign) {
            if (!grapesEditor) {
                return;
            }
            nextDesign = nextDesign || {};
            var components = '';
            var styles = '';
            if (Array.isArray(nextDesign.components)) {
                components = nextDesign.components.length ? nextDesign.components : (nextDesign.html || '');
            } else {
                components = nextDesign.components || nextDesign.html || '';
            }
            if (Array.isArray(nextDesign.styles)) {
                styles = nextDesign.styles.length ? nextDesign.styles : (nextDesign.css || '');
            } else {
                styles = nextDesign.styles || nextDesign.css || '';
            }
            grapesEditor.setComponents(components);
            grapesEditor.setStyle(styles);
            syncGrapes();
        }

        function applyGrapesCanvasTheme() {
            if (!grapesEditor || !grapesEditor.Canvas) {
                return;
            }
            var doc = grapesEditor.Canvas.getDocument();
            if (!doc || !doc.head) {
                return;
            }
            var style = doc.getElementById('apcampaign-canvas-theme');
            if (!style) {
                style = doc.createElement('style');
                style.id = 'apcampaign-canvas-theme';
                doc.head.appendChild(style);
            }
            var pasteboard = '#dbe3ef';
            style.textContent = 'html{background:' + pasteboard + ' !important;}body{box-sizing:border-box !important;max-width:680px !important;min-height:640px !important;margin:28px auto !important;padding:28px !important;background:#ffffff !important;color:#111827 !important;box-shadow:none !important;border-radius:2px !important;}body > *{box-sizing:border-box;}body:empty:before{content:"Drop blocks here";display:block;padding:60px 20px;text-align:center;color:#64748b;border:1px dashed #cbd5e1;border-radius:6px;font-family:Arial,sans-serif;}';
        }

        function grapesStyleValue(component, key, fallback) {
            var style = component && component.getStyle ? component.getStyle() : {};
            return style[key] || fallback || '';
        }

        function grapesPxValue(component, key, fallback) {
            var raw = String(grapesStyleValue(component, key, fallback || 0));
            var match = raw.match(/-?[0-9.]+/);
            return match ? match[0] : String(fallback || 0);
        }

        function grapesSelectedText(component) {
            if (!component) {
                return '';
            }
            var children = component.components ? component.components() : null;
            if (children && children.length === 1 && children.at(0).get('type') === 'textnode') {
                return children.at(0).get('content') || '';
            }
            return component.get('content') || '';
        }

        function grapesLabel(component) {
            if (!component) {
                return 'Block';
            }
            if (isHeroComponent(component)) {
                return 'Hero';
            }
            if (component.is && component.is('image')) {
                return 'Image';
            }
            var tag = String(component.get('tagName') || '').toLowerCase();
            if (tag === 'a') {
                return 'Button or Link';
            }
            if (/^h[1-6]$/.test(tag)) {
                return 'Heading';
            }
            if (tag === 'p') {
                return 'Text';
            }
            if (tag === 'section' || tag === 'div' || tag === 'table' || tag === 'td') {
                return 'Layout Block';
            }
            return blockLabel(tag || component.get('type') || 'block');
        }

        function isHeroComponent(component) {
            var attrs = component && component.getAttributes ? component.getAttributes() : {};
            return attrs && attrs['data-apcampaign-hero'];
        }

        function containingHeroComponent(component) {
            var current = component;
            while (current) {
                if (isHeroComponent(current)) {
                    return current;
                }
                current = current.parent ? current.parent() : null;
            }
            return null;
        }

        function heroOverlay(component) {
            if (!component || !component.find) {
                return null;
            }
            var overlays = component.find('[data-apcampaign-hero-overlay]');
            return overlays && overlays.length ? overlays[0] : null;
        }

        function heroOpacity(component) {
            var overlay = heroOverlay(component);
            var opacity = overlay && overlay.getStyle ? overlay.getStyle().opacity : '';
            var numeric = parseFloat(opacity);
            return isNaN(numeric) ? 35 : Math.round(numeric * 100);
        }

        function backgroundImageUrl(component) {
            var raw = grapesStyleValue(component, 'background-image', '');
            var matches = String(raw).match(/url\((['"]?)(.*?)\1\)/ig);
            var last = matches && matches.length ? matches[matches.length - 1] : '';
            var match = String(last).match(/^url\((['"]?)(.*?)\1\)$/i);
            return match ? match[2] : '';
        }

        function backgroundOverlay(component) {
            var raw = grapesStyleValue(component, 'background-image', '');
            var match = String(raw).match(/linear-gradient\(\s*rgba\(\s*0\s*,\s*0\s*,\s*0\s*,\s*([0-9.]+)\s*\)/i);
            var alpha = match ? parseFloat(match[1]) : 0;
            return isNaN(alpha) ? 0 : Math.round(alpha * 100);
        }

        function overlayGradient(value) {
            var alpha = Math.max(0, Math.min(100, parseInt(value, 10) || 0)) / 100;
            return 'linear-gradient(rgba(0,0,0,' + alpha + '), rgba(0,0,0,' + alpha + '))';
        }

        function setBlockBackgroundImage(component, url, overlay) {
            if (!component || !component.addStyle) {
                return;
            }
            component.addStyle({
                'background-image': url ? overlayGradient(overlay) + ', url("' + String(url).replace(/"/g, '%22') + '")' : 'none',
                'background-size': grapesStyleValue(component, 'background-size', 'cover') || 'cover',
                'background-position': grapesStyleValue(component, 'background-position', 'center') || 'center',
                'background-repeat': 'no-repeat'
            });
        }

        function setHeroBackgroundImage(component, url) {
            var style = {
                'background-image': url ? 'url("' + String(url).replace(/"/g, '%22') + '")' : 'none',
                'background-size': grapesStyleValue(component, 'background-size', 'cover') || 'cover',
                'background-position': grapesStyleValue(component, 'background-position', 'center') || 'center',
                'background-repeat': 'no-repeat'
            };
            component.addStyle(style);
        }

        function setHeroOpacity(component, value) {
            var overlay = heroOverlay(component);
            if (!overlay) {
                return;
            }
            var numeric = Math.max(0, Math.min(100, parseInt(value, 10) || 0));
            overlay.addStyle({opacity: String(numeric / 100)});
        }

        function firstImageComponent(component) {
            if (!component) {
                return null;
            }
            var tag = String(component.get ? (component.get('tagName') || '') : '').toLowerCase();
            if ((component.is && component.is('image')) || tag === 'img') {
                return component;
            }
            var tagMatches = component.find ? component.find('img') : [];
            if (tagMatches && tagMatches.length) {
                return tagMatches[0];
            }
            var images = component.findType ? component.findType('image') : [];
            return images && images.length ? images[0] : null;
        }

        function componentHasImage(component) {
            return !!firstImageComponent(component);
        }

        function setImageComponentSource(imageComponent, attachment) {
            if (!imageComponent || !attachment) {
                return;
            }
            var attrs = imageComponent.getAttributes ? imageComponent.getAttributes() : {};
            var nextAttrs = Object.assign({}, attrs, {
                src: attachment.url || '',
                alt: attachment.alt || attachment.title || attrs.alt || ''
            });
            if (imageComponent.addAttributes) {
                imageComponent.addAttributes(nextAttrs);
            }
            if (imageComponent.set) {
                imageComponent.set('attributes', nextAttrs);
                imageComponent.set('src', attachment.url || '');
            }
        }

        function apiUrl(path) {
            if (!window.wpApiSettings || !window.wpApiSettings.root) {
                return '';
            }
            return window.wpApiSettings.root.replace(/\/$/, '') + '/' + path.replace(/^\//, '');
        }

        function apiHeaders(extra) {
            return Object.assign(window.wpApiSettings && window.wpApiSettings.nonce ? {'X-WP-Nonce': window.wpApiSettings.nonce} : {}, extra || {});
        }

        function apiGet(path) {
            var url = apiUrl(path);
            if (!url) {
                return Promise.reject(new Error('WordPress content API unavailable.'));
            }
            return fetch(url, {
                credentials: 'same-origin',
                headers: apiHeaders()
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('Could not load WordPress content.');
                }
                return response.json();
            });
        }

        function containingTeaserComponent(component) {
            var current = component;
            while (current) {
                var attrs = current.getAttributes ? current.getAttributes() : {};
                if (attrs && attrs['data-apcampaign-teaser']) {
                    return current;
                }
                current = current.parent ? current.parent() : null;
            }
            return null;
        }

        function teaserChild(component, selector) {
            var found = component && component.find ? component.find(selector) : [];
            return found && found.length ? found[0] : null;
        }

        function teaserChildrenByAttr(component, tagName, attrName) {
            var found = component && component.find ? component.find(tagName) : [];
            return (found || []).filter(function (child) {
                var attrs = child && child.getAttributes ? child.getAttributes() : {};
                return attrs && attrs[attrName];
            });
        }

        function teaserComponentText(component, selector) {
            var child = teaserChild(component, selector);
            if (!child) {
                return '';
            }
            var content = child.get ? String(child.get('content') || '') : '';
            if (content) {
                return content;
            }
            return child.toHTML ? stripRendered(child.toHTML()) : '';
        }

        function teaserData(component) {
            var attrs = component && component.getAttributes ? component.getAttributes() : {};
            var links = teaserChildrenByAttr(component, 'a', 'data-apcampaign-teaser-link');
            var link = links && links.length ? links[0] : null;
            var imageChildren = teaserChildrenByAttr(component, 'img', 'data-apcampaign-teaser-image');
            var image = imageChildren && imageChildren.length ? imageChildren[0] : null;
            var linkAttrs = link && link.getAttributes ? link.getAttributes() : {};
            var imageAttrs = image && image.getAttributes ? image.getAttributes() : {};
            return {
                category: attrs['data-apcampaign-teaser-category'] || '',
                sourceUrl: attrs['data-apcampaign-teaser-source-url'] || '',
                label: teaserComponentText(component, '[data-apcampaign-teaser-label]') || 'Story',
                title: teaserComponentText(component, '[data-apcampaign-teaser-title]') || 'Article or issue teaser',
                excerpt: teaserComponentText(component, '[data-apcampaign-teaser-excerpt]') || '',
                link: linkAttrs.href || '',
                image: imageAttrs.src || '',
                alt: imageAttrs.alt || ''
            };
        }

        function setTeaserData(component, data) {
            if (!component || !data) {
                return;
            }
            var current = teaserData(component);
            var next = Object.assign({}, current, data);
            component.addAttributes({
                'data-apcampaign-teaser-category': next.category || '',
                'data-apcampaign-teaser-source-url': next.sourceUrl || ''
            });
            var label = teaserChild(component, '[data-apcampaign-teaser-label]');
            var title = teaserChild(component, '[data-apcampaign-teaser-title]');
            var excerpt = teaserChild(component, '[data-apcampaign-teaser-excerpt]');
            var imageChildren = teaserChildrenByAttr(component, 'img', 'data-apcampaign-teaser-image');
            var image = imageChildren && imageChildren.length ? imageChildren[0] : null;
            if (label) {
                label.components(next.label || 'Story');
            }
            if (title) {
                title.components(next.title || 'Article or issue teaser');
            }
            if (excerpt) {
                excerpt.components(next.excerpt || 'Short summary text that drives the reader to click through.');
            }
            (links || []).forEach(function (linkComponent) {
                linkComponent.addAttributes({href: next.link || '#'});
            });
            if (image) {
                image.addAttributes({src: next.image || 'https://via.placeholder.com/180x120?text=Story', alt: next.alt || next.title || ''});
            }
        }

        function stripRendered(value) {
            var div = document.createElement('div');
            div.innerHTML = String(value || '');
            return (div.textContent || div.innerText || '').replace(/\s+/g, ' ').trim();
        }

        function postToTeaserData(post) {
            var media = post && post._embedded && post._embedded['wp:featuredmedia'] && post._embedded['wp:featuredmedia'][0] ? post._embedded['wp:featuredmedia'][0] : null;
            var category = post && post._embedded && post._embedded['wp:term'] && post._embedded['wp:term'][0] && post._embedded['wp:term'][0][0] ? post._embedded['wp:term'][0][0].name : 'Story';
            return {
                label: category || 'Story',
                title: stripRendered(post && post.title ? post.title.rendered : ''),
                excerpt: stripRendered(post && post.excerpt ? post.excerpt.rendered : ''),
                link: post && post.link ? post.link : '',
                image: media && media.source_url ? media.source_url : '',
                alt: media && media.alt_text ? media.alt_text : stripRendered(post && post.title ? post.title.rendered : '')
            };
        }

        function applyTeaserPost(teaserComponent, postId) {
            if (!teaserComponent || !postId) {
                return;
            }
            apiGet('wp/v2/posts/' + encodeURIComponent(postId) + '?_embed=1').then(function (post) {
                setTeaserData(teaserComponent, postToTeaserData(post));
                grapesEditor.select(teaserComponent);
                renderGrapesInspector(teaserComponent);
                syncGrapes();
            }).catch(function (error) {
                showTeaserStatus(error.message || 'Could not load story.');
            });
        }

        function fetchExternalTeaser(teaserComponent, sourceUrl) {
            sourceUrl = String(sourceUrl || '').trim();
            if (!teaserComponent || !sourceUrl) {
                return;
            }
            showTeaserStatus('Fetching story preview...');
            apiGet('oembed/1.0/proxy?url=' + encodeURIComponent(sourceUrl)).then(function (item) {
                setTeaserData(teaserComponent, {
                    sourceUrl: sourceUrl,
                    label: 'Story',
                    title: stripRendered(item.title || ''),
                    excerpt: stripRendered(item.author_name || item.provider_name || ''),
                    link: sourceUrl,
                    image: item.thumbnail_url || '',
                    alt: stripRendered(item.title || '')
                });
                grapesEditor.select(teaserComponent);
                renderGrapesInspector(teaserComponent);
                syncGrapes();
            }).catch(function () {
                setTeaserData(teaserComponent, {sourceUrl: sourceUrl, link: sourceUrl});
                grapesEditor.select(teaserComponent);
                renderGrapesInspector(teaserComponent);
                showTeaserStatus('Preview was not available. The link was saved; fill in the title, excerpt, and image manually.');
                syncGrapes();
            });
        }

        function showTeaserStatus(message) {
            var status = qs('[data-apcampaign-teaser-status]', resultBox);
            if (status) {
                status.textContent = message || '';
            }
        }

        function renderTeaserSourceControls(teaserComponent) {
            var data = teaserData(teaserComponent);
            return controlSection('Story Source') +
                '<label>WordPress Category<select data-apcampaign-teaser-category><option value="' + escapeHtml(data.category || '') + '">Loading categories...</option></select></label>' +
                '<label>Local WordPress Story<select data-apcampaign-teaser-post><option value="">Choose a category first</option></select></label>' +
                '<div class="apcampaign-property-actions"><button type="button" data-apcampaign-teaser-load>Load Latest Stories</button></div>' +
                controlInput('External Story URL', 'source_url', data.sourceUrl || '', 'https:// story URL', 'url').replace(/data-option=/g, 'data-apcampaign-teaser-external=') +
                '<div class="apcampaign-property-actions"><button type="button" data-apcampaign-teaser-fetch>Fetch URL</button></div>' +
                '<p class="apcampaign-drag-note" data-apcampaign-teaser-status>Pick a local post or paste an external story URL. You can still override the fields below.</p>' +
                controlSection('Story Content') +
                controlInput('Label', 'label', data.label || 'Story', 'Story').replace(/data-option=/g, 'data-apcampaign-teaser-field=') +
                controlInput('Headline', 'title', data.title || '', 'Article headline').replace(/data-option=/g, 'data-apcampaign-teaser-field=') +
                controlTextarea('Excerpt', 'excerpt', data.excerpt || '', 4).replace(/data-option=/g, 'data-apcampaign-teaser-field=') +
                controlInput('Story Link', 'link', data.link || '', 'https://', 'url').replace(/data-option=/g, 'data-apcampaign-teaser-field=') +
                controlInput('Image URL', 'image', data.image || '', 'https:// image URL', 'url').replace(/data-option=/g, 'data-apcampaign-teaser-field=') +
                '<div class="apcampaign-property-actions"><button type="button" data-apcampaign-teaser-image>Choose Teaser Image</button></div>';
        }

        function hydrateTeaserPicker(teaserComponent) {
            var categorySelect = qs('[data-apcampaign-teaser-category]', resultBox);
            var postSelect = qs('[data-apcampaign-teaser-post]', resultBox);
            if (!teaserComponent || !categorySelect || !postSelect) {
                return;
            }
            var current = teaserData(teaserComponent);
            apiGet('wp/v2/categories?per_page=100&orderby=name&order=asc').then(function (categories) {
                var options = '<option value="">All categories</option>';
                categories.forEach(function (category) {
                    options += '<option value="' + escapeHtml(category.id) + '"' + (String(category.id) === String(current.category) ? ' selected' : '') + '>' + escapeHtml(category.name) + '</option>';
                });
                categorySelect.innerHTML = options;
                loadTeaserPosts(teaserComponent, categorySelect.value);
            }).catch(function () {
                categorySelect.innerHTML = '<option value="">Categories unavailable</option>';
                postSelect.innerHTML = '<option value="">Stories unavailable</option>';
            });
        }

        function loadTeaserPosts(teaserComponent, categoryId) {
            var postSelect = qs('[data-apcampaign-teaser-post]', resultBox);
            if (!teaserComponent || !postSelect) {
                return;
            }
            showTeaserStatus('Loading stories...');
            var path = 'wp/v2/posts?per_page=20&_embed=1&orderby=date&order=desc';
            if (categoryId) {
                path += '&categories=' + encodeURIComponent(categoryId);
            }
            apiGet(path).then(function (posts) {
                if (!posts.length) {
                    postSelect.innerHTML = '<option value="">No stories found</option>';
                    showTeaserStatus('No local stories found for this category.');
                    return;
                }
                postSelect.innerHTML = '<option value="">Choose a story...</option>' + posts.map(function (post) {
                    return '<option value="' + escapeHtml(post.id) + '">' + escapeHtml(stripRendered(post.title && post.title.rendered ? post.title.rendered : 'Untitled')) + '</option>';
                }).join('');
                showTeaserStatus('Choose a story to populate this teaser.');
            }).catch(function (error) {
                postSelect.innerHTML = '<option value="">Stories unavailable</option>';
                showTeaserStatus(error.message || 'Could not load stories.');
            });
        }

        function imageBlockComponent(component) {
            var current = component;
            while (current) {
                var attrs = current.getAttributes ? current.getAttributes() : {};
                if (attrs && attrs['data-apcampaign-image-block']) {
                    return current;
                }
                current = current.parent ? current.parent() : null;
            }
            return null;
        }

        function socialPlatforms() {
            return [
                {key: 'facebook', label: 'Facebook', icon: 'f'},
                {key: 'instagram', label: 'Instagram', icon: 'ig'},
                {key: 'linkedin', label: 'LinkedIn', icon: 'in'},
                {key: 'youtube', label: 'YouTube', icon: 'yt'},
                {key: 'website', label: 'Website', icon: 'www'}
            ];
        }

        function isSocialComponent(component) {
            var attrs = component && component.getAttributes ? component.getAttributes() : {};
            return attrs && attrs['data-apcampaign-social-block'];
        }

        function containingSocialComponent(component) {
            var current = component;
            while (current) {
                if (isSocialComponent(current)) {
                    return current;
                }
                current = current.parent ? current.parent() : null;
            }
            return null;
        }

        function socialDisplay(component) {
            var attrs = component && component.getAttributes ? component.getAttributes() : {};
            return attrs['data-apcampaign-social-display'] || 'names';
        }

        function socialData(component) {
            var data = {};
            socialPlatforms().forEach(function (platform) {
                data[platform.key] = {url: '', label: platform.label};
            });
            if (!component || !component.find) {
                return data;
            }
            component.find('a').forEach(function (link) {
                var attrs = link.getAttributes ? link.getAttributes() : {};
                var key = attrs['data-apcampaign-social-key'];
                if (!key || !data[key]) {
                    return;
                }
                data[key].url = attrs.href || '';
                data[key].label = attrs['data-apcampaign-social-label'] || link.get('content') || data[key].label;
            });
            return data;
        }

        function socialAnchorHtml(platform, item, display) {
            var url = String(item.url || '').trim();
            if (!url) {
                return '';
            }
            var label = String(item.label || platform.label).trim() || platform.label;
            var icon = '<span style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;margin-right:' + (display === 'icons' ? '0' : '7px') + ';border-radius:999px;background:#0d1b2a;color:#ffffff;font-size:11px;font-weight:700;text-transform:uppercase;">' + escapeHtml(platform.icon) + '</span>';
            var text = '<span>' + escapeHtml(label) + '</span>';
            var body = display === 'icons' ? icon : (display === 'icons_names' ? icon + text : text);
            return '<a data-apcampaign-social-key="' + escapeHtml(platform.key) + '" data-apcampaign-social-label="' + escapeHtml(label) + '" href="' + escapeHtml(url) + '" style="display:inline-flex;align-items:center;margin:4px 6px;color:#0077ff;text-decoration:none;font-weight:700;font-family:Arial,sans-serif;">' + body + '</a>';
        }

        function renderSocialComponents(component, data, display) {
            component.addAttributes({'data-apcampaign-social-display': display});
            component.components(socialPlatforms().map(function (platform) {
                return socialAnchorHtml(platform, data[platform.key] || {url: '', label: platform.label}, display);
            }).join(''));
        }

        function openCampaignMediaPicker(options) {
            options = options || {};
            if (window.apCampaignMedia && typeof window.apCampaignMedia.open === 'function') {
                return window.apCampaignMedia.open(options);
            }
            if (!(window.apCampaignApp || {}).mediaEnabled || !window.wpApiSettings || !window.wpApiSettings.root || !window.wpApiSettings.nonce) {
                if (resultBox) {
                    resultBox.innerHTML = '<p><strong>Media library unavailable.</strong></p><p>Your account must be allowed to upload files.</p>';
                }
                return null;
            }
            var modal = document.createElement('div');
            modal.className = 'apcampaign-media-card-backdrop';
            modal.innerHTML = '<section class="apcampaign-media-card" role="dialog" aria-modal="true"><header><div><p>WordPress Media</p><h2>' + escapeHtml(options.title || 'Choose campaign image') + '</h2></div><button type="button" data-apcampaign-media-close aria-label="Close">×</button></header><div class="apcampaign-media-card-body"><label class="apcampaign-media-upload">Upload image<input type="file" accept="image/*" data-apcampaign-media-file></label><label class="apcampaign-media-url">Image URL<input type="url" data-apcampaign-media-url placeholder="https://"></label><button type="button" data-apcampaign-media-use-url>Use URL</button><div class="apcampaign-media-status" data-apcampaign-media-status>Loading recent images...</div><div class="apcampaign-media-grid" data-apcampaign-media-grid></div></div></section>';
            document.body.appendChild(modal);
            document.body.classList.add('apcampaign-media-open');
            var grid = qs('[data-apcampaign-media-grid]', modal);
            var status = qs('[data-apcampaign-media-status]', modal);
            var urlInput = qs('[data-apcampaign-media-url]', modal);

            function close() {
                document.body.classList.remove('apcampaign-media-open');
                modal.remove();
            }

            function choose(attachment) {
                if (typeof options.onSelect === 'function') {
                    options.onSelect(attachment);
                }
                close();
            }

            function apiUrl(path) {
                return window.wpApiSettings.root.replace(/\/$/, '') + '/' + path.replace(/^\//, '');
            }

            function headers(extra) {
                return Object.assign({'X-WP-Nonce': window.wpApiSettings.nonce}, extra || {});
            }

            function imageUrl(item) {
                return (item.media_details && item.media_details.sizes && item.media_details.sizes.thumbnail && item.media_details.sizes.thumbnail.source_url) || item.source_url || '';
            }

            function renderItems(items) {
                grid.innerHTML = '';
                if (!items.length) {
                    status.textContent = 'No recent images yet. Upload one or paste an image URL.';
                    return;
                }
                status.textContent = 'Choose a recent image or upload a new one.';
                items.forEach(function (item) {
                    var thumb = imageUrl(item);
                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'apcampaign-media-thumb';
                    button.innerHTML = '<img src="' + escapeHtml(thumb) + '" alt=""><span>' + escapeHtml((item.title && item.title.rendered) || 'Image') + '</span>';
                    button.addEventListener('click', function () {
                        choose({url: item.source_url || thumb, alt: item.alt_text || '', title: (item.title && item.title.rendered) || ''});
                    });
                    grid.appendChild(button);
                });
            }

            function loadRecent() {
                fetch(apiUrl('wp/v2/media?media_type=image&per_page=24&_fields=id,source_url,alt_text,title,media_details'), {
                    credentials: 'same-origin',
                    headers: headers()
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('Could not load images.');
                    }
                    return response.json();
                }).then(renderItems).catch(function (error) {
                    status.textContent = error.message || 'Could not load images.';
                });
            }

            modal.addEventListener('click', function (event) {
                if (event.target === modal || event.target.matches('[data-apcampaign-media-close]')) {
                    close();
                }
                if (event.target.matches('[data-apcampaign-media-use-url]')) {
                    var url = String(urlInput.value || '').trim();
                    if (url) {
                        choose({url: url, alt: '', title: ''});
                    }
                }
            });
            qs('[data-apcampaign-media-file]', modal).addEventListener('change', function (event) {
                var file = event.target.files && event.target.files[0];
                if (!file) {
                    return;
                }
                status.textContent = 'Uploading...';
                var formData = new FormData();
                formData.append('file', file, file.name);
                fetch(apiUrl('wp/v2/media'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: headers(),
                    body: formData
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('Upload failed.');
                    }
                    return response.json();
                }).then(function (item) {
                    choose({url: item.source_url || '', alt: item.alt_text || '', title: (item.title && item.title.rendered) || file.name});
                }).catch(function (error) {
                    status.textContent = error.message || 'Upload failed.';
                });
            });
            loadRecent();
            return {close: close};
        }

        function openImagePickerForComponent(component) {
            var imageComponent = firstImageComponent(component);
            if (!imageComponent) {
                var imageBlock = imageBlockComponent(component);
                imageComponent = firstImageComponent(imageBlock);
            }
            if (!imageComponent) {
                if (resultBox) {
                    resultBox.innerHTML = '<p><strong>Select an image block.</strong></p><p>Choose an image block or its upload button, then open media again.</p>';
                }
                return;
            }
            openCampaignMediaPicker({
                title: 'Choose campaign image',
                buttonText: 'Use this image',
                onSelect: function (attachment) {
                    setImageComponentSource(imageComponent, attachment);
                    var wrapper = imageComponent.closest('[data-apcampaign-image-block]');
                    if (wrapper) {
                        wrapper.find('[data-apcampaign-media-button]').forEach(function (buttonComponent) {
                            buttonComponent.remove();
                        });
                    }
                    syncGrapes();
                }
            });
        }

        function renderGrapesInspector(component) {
            if (!resultBox) {
                return;
            }
            if (!component) {
                resultBox.innerHTML = '<div class="apcampaign-no-selection compact"><strong>Select a block</strong><p>Choose content in the canvas to edit text, images, colors, spacing, and links.</p></div>';
                return;
            }
            var attrs = component.getAttributes ? component.getAttributes() : {};
            var isImage = component.is && component.is('image');
            var nestedImageComponent = isImage ? component : firstImageComponent(component);
            var imageAttrs = nestedImageComponent && nestedImageComponent.getAttributes ? nestedImageComponent.getAttributes() : {};
            var tag = String(component.get('tagName') || '').toLowerCase();
            var heroComponent = containingHeroComponent(component);
            var socialComponent = containingSocialComponent(component);
            var teaserComponent = containingTeaserComponent(component);
            var canText = !teaserComponent && !isImage && ['h1', 'h2', 'h3', 'h4', 'p', 'a', 'span', 'button'].indexOf(tag) !== -1;
            var fields = '<div class="apcampaign-property-editor" data-apcampaign-grapes-inspector><div class="apcampaign-property-head"><strong>Block Settings</strong><span>' + escapeHtml(grapesLabel(component)) + '</span></div>';
            if (teaserComponent) {
                fields += renderTeaserSourceControls(teaserComponent);
            }
            if (socialComponent) {
                var links = socialData(socialComponent);
                fields += controlSection('Social Links');
                fields += controlSelect('Display', 'social-display', socialDisplay(socialComponent), [{value: 'names', label: 'Names'}, {value: 'icons', label: 'Icons'}, {value: 'icons_names', label: 'Icons + Names'}]).replace(/data-option=/g, 'data-grapes-social-display=');
                socialPlatforms().forEach(function (platform) {
                    fields += controlInput(platform.label + ' URL', platform.key + '-url', links[platform.key].url, 'https://', 'url').replace(/data-option=/g, 'data-grapes-social-url="' + platform.key + '" data-ignore=');
                    fields += controlInput(platform.label + ' Label', platform.key + '-label', links[platform.key].label, platform.label).replace(/data-option=/g, 'data-grapes-social-label="' + platform.key + '" data-ignore=');
                });
            }
            if (canText) {
                fields += controlTextarea('Text', 'content', grapesSelectedText(component), tag === 'p' ? 5 : 2).replace(/data-option=/g, 'data-grapes-content=');
            }
            if (tag === 'a') {
                fields += controlInput('Link URL', 'href', attrs.href || '', 'https://', 'url').replace(/data-option=/g, 'data-grapes-attr=');
            }
            if (nestedImageComponent && !teaserComponent) {
                fields += controlSection('Image');
                fields += controlInput('Image URL', 'src', imageAttrs.src || '', 'https:// image URL', 'url').replace(/data-option=/g, 'data-grapes-image-attr=');
                fields += controlInput('Alt Text', 'alt', imageAttrs.alt || '', 'Describe the image').replace(/data-option=/g, 'data-grapes-image-attr=');
                fields += '<div class="apcampaign-property-actions"><button type="button" data-apcampaign-grapes-media>Choose Image</button></div>';
            }
            if (heroComponent) {
                fields += controlSection('Hero Background');
                fields += controlInput('Background Image URL', 'background-image-url', backgroundImageUrl(heroComponent), 'https:// image URL', 'url').replace(/data-option=/g, 'data-grapes-hero-bg=');
                fields += '<div class="apcampaign-property-actions"><button type="button" data-apcampaign-grapes-hero-media>Choose Background Image</button></div>';
                fields += controlSelect('Image Position', 'background-position', grapesStyleValue(heroComponent, 'background-position', 'center'), [{value: 'center', label: 'Center'}, {value: 'center top', label: 'Top'}, {value: 'center bottom', label: 'Bottom'}, {value: 'left center', label: 'Left'}, {value: 'right center', label: 'Right'}]).replace(/data-option=/g, 'data-grapes-style=');
                fields += controlRange('Image Overlay', 'hero-opacity', heroOpacity(heroComponent), 0, 100, '%').replace(/data-option=/g, 'data-grapes-hero-opacity=');
            } else if (!isImage) {
                fields += controlSection('Block Background');
                fields += controlInput('Background Image URL', 'background-image-url', backgroundImageUrl(component), 'https:// image URL', 'url').replace(/data-option=/g, 'data-grapes-bg=');
                fields += '<div class="apcampaign-property-actions"><button type="button" data-apcampaign-grapes-bg-media>Choose Background Image</button></div>';
                fields += controlSelect('Image Position', 'background-position', grapesStyleValue(component, 'background-position', 'center'), [{value: 'center', label: 'Center'}, {value: 'center top', label: 'Top'}, {value: 'center bottom', label: 'Bottom'}, {value: 'left center', label: 'Left'}, {value: 'right center', label: 'Right'}]).replace(/data-option=/g, 'data-grapes-style=');
                fields += controlRange('Image Overlay', 'background-overlay', backgroundOverlay(component), 0, 100, '%').replace(/data-option=/g, 'data-grapes-bg-overlay=');
            }
            fields += controlSection('Appearance');
            fields += controlColor('Text Color', 'color', grapesStyleValue(component, 'color', '#111827')).replace(/data-option=/g, 'data-grapes-style=');
            fields += controlColor('Background', 'background-color', grapesStyleValue(component, 'background-color', '#ffffff')).replace(/data-option=/g, 'data-grapes-style=');
            fields += controlSelect('Alignment', 'text-align', grapesStyleValue(component, 'text-align', 'left'), [{value: 'left', label: 'Left'}, {value: 'center', label: 'Center'}, {value: 'right', label: 'Right'}]).replace(/data-option=/g, 'data-grapes-style=');
            fields += controlRange('Font Size', 'font-size', grapesPxValue(component, 'font-size', 16), 10, 72, 'px').replace(/data-option=/g, 'data-grapes-style-px=');
            fields += controlRange('Padding', 'padding', grapesPxValue(component, 'padding', 12), 0, 120, 'px').replace(/data-option=/g, 'data-grapes-style-px=');
            fields += controlRange('Minimum Height', 'min-height', grapesPxValue(component, 'min-height', 0), 0, 900, 'px').replace(/data-option=/g, 'data-grapes-style-px=');
            fields += controlRange('Corner Radius', 'border-radius', grapesPxValue(component, 'border-radius', 0), 0, 60, 'px').replace(/data-option=/g, 'data-grapes-style-px=');
            fields += '<div class="apcampaign-property-actions"><button type="button" data-apcampaign-grapes-duplicate>Duplicate</button><button type="button" data-apcampaign-grapes-delete>Delete</button></div></div>';
            resultBox.innerHTML = fields;
            if (teaserComponent) {
                hydrateTeaserPicker(teaserComponent);
            }
        }

        function applyGrapesInspectorInput(target) {
            if (!grapesEditor) {
                return;
            }
            var component = grapesEditor.getSelected();
            if (!component) {
                return;
            }
            var styleKey = target.getAttribute('data-grapes-style');
            var pxKey = target.getAttribute('data-grapes-style-px');
            var attrKey = target.getAttribute('data-grapes-attr');
            var imageAttrKey = target.getAttribute('data-grapes-image-attr');
            var contentKey = target.getAttribute('data-grapes-content');
            var heroBgKey = target.getAttribute('data-grapes-hero-bg');
            var heroOpacityKey = target.getAttribute('data-grapes-hero-opacity');
            var blockBgKey = target.getAttribute('data-grapes-bg');
            var blockBgOverlayKey = target.getAttribute('data-grapes-bg-overlay');
            var heroComponent = containingHeroComponent(component);
            var socialComponent = containingSocialComponent(component);
            var socialDisplayKey = target.getAttribute('data-grapes-social-display');
            var socialUrlKey = target.getAttribute('data-grapes-social-url');
            var socialLabelKey = target.getAttribute('data-grapes-social-label');
            var teaserComponent = containingTeaserComponent(component);
            var teaserFieldKey = target.getAttribute('data-apcampaign-teaser-field');
            var teaserExternalKey = target.getAttribute('data-apcampaign-teaser-external');
            var teaserCategory = target.getAttribute('data-apcampaign-teaser-category');
            var teaserPost = target.getAttribute('data-apcampaign-teaser-post');
            if (teaserComponent && (teaserFieldKey || teaserExternalKey || teaserCategory || teaserPost)) {
                if (teaserFieldKey) {
                    if (teaserFieldKey === 'link') {
                        teaserComponent.find('[data-apcampaign-teaser-link]').forEach(function (linkComponent) {
                            linkComponent.addAttributes({href: target.value || '#'});
                        });
                    } else if (teaserFieldKey === 'image') {
                        var teaserImage = teaserChild(teaserComponent, '[data-apcampaign-teaser-image]') || firstImageComponent(teaserComponent);
                        if (teaserImage) {
                            setImageComponentSource(teaserImage, {url: target.value || 'https://via.placeholder.com/180x120?text=Story', alt: teaserData(teaserComponent).alt || teaserData(teaserComponent).title || ''});
                        }
                    } else {
                        var data = {};
                        data[teaserFieldKey] = target.value;
                        setTeaserData(teaserComponent, data);
                    }
                }
                if (teaserExternalKey) {
                    setTeaserData(teaserComponent, {sourceUrl: target.value});
                }
                if (teaserCategory !== null) {
                    setTeaserData(teaserComponent, {category: target.value});
                    loadTeaserPosts(teaserComponent, target.value);
                }
                if (teaserPost !== null && target.value) {
                    applyTeaserPost(teaserComponent, target.value);
                    return;
                }
                syncGrapes();
                return;
            }
            if (socialComponent && (socialDisplayKey || socialUrlKey || socialLabelKey)) {
                var links = socialData(socialComponent);
                if (socialUrlKey && links[socialUrlKey]) {
                    links[socialUrlKey].url = target.value;
                }
                if (socialLabelKey && links[socialLabelKey]) {
                    links[socialLabelKey].label = target.value;
                }
                renderSocialComponents(socialComponent, links, socialDisplayKey ? target.value : socialDisplay(socialComponent));
                grapesEditor.select(socialComponent);
                syncGrapes();
                return;
            }
            if (heroBgKey) {
                setHeroBackgroundImage(heroComponent || component, target.value);
            }
            if (heroOpacityKey) {
                setHeroOpacity(heroComponent || component, target.value);
                var heroOutput = target.parentNode ? qs('output', target.parentNode) : null;
                if (heroOutput) {
                    heroOutput.textContent = target.value + '%';
                }
            }
            if (blockBgKey) {
                setBlockBackgroundImage(component, target.value, backgroundOverlay(component));
            }
            if (blockBgOverlayKey) {
                setBlockBackgroundImage(component, backgroundImageUrl(component), target.value);
                var blockOutput = target.parentNode ? qs('output', target.parentNode) : null;
                if (blockOutput) {
                    blockOutput.textContent = target.value + '%';
                }
            }
            if (styleKey) {
                var style = {};
                style[styleKey] = target.value;
                (heroComponent && styleKey === 'background-position' ? heroComponent : component).addStyle(style);
            }
            if (pxKey) {
                var pxStyle = {};
                pxStyle[pxKey] = target.value === '0' ? '0' : target.value + 'px';
                component.addStyle(pxStyle);
                var output = target.parentNode ? qs('output', target.parentNode) : null;
                if (output) {
                    output.textContent = target.value + 'px';
                }
            }
            if (attrKey) {
                var attrs = {};
                attrs[attrKey] = target.value;
                component.addAttributes(attrs);
            }
            if (imageAttrKey) {
                var imageComponent = firstImageComponent(component) || component;
                var imageAttrs = {};
                imageAttrs[imageAttrKey] = target.value;
                imageComponent.addAttributes(imageAttrs);
            }
            if (contentKey) {
                component.components(target.value);
            }
            syncGrapes();
        }

        function applyTemplate(template) {
            if (!template) {
                return;
            }
            recordHistory();
            modeField.value = template.composer_mode === 'raw_html' ? 'raw_html' : 'block';
            if (modeField.value === 'raw_html') {
                raw.value = template.raw_html || '';
                useGrapes = false;
                form.classList.remove('is-grapes');
            } else if ((template.design || {}).editor === 'grapesjs') {
                useGrapes = !!window.grapesjs;
                design = cloneDesign(template.design || {editor: 'grapesjs', version: 1});
                if (useGrapes) {
                    initGrapes();
                    loadGrapesDesign(design);
                }
            } else {
                useGrapes = false;
                form.classList.remove('is-grapes');
                design = cloneDesign(template.design || {blocks: []});
            }
            selectedIndex = -1;
            switchMode(modeField.value);
            if (useGrapes) {
                syncGrapes();
            } else {
                paint();
            }
            updateRawMeta();
            if (resultBox) {
                resultBox.innerHTML = '<p><strong>Template applied.</strong></p><p>' + escapeHtml(template.name || 'Template') + '</p>';
            }
            closeTemplateModal();
        }

        function closeTemplateModal() {
            if (templateModal && templateModal.parentNode) {
                templateModal.parentNode.removeChild(templateModal);
            }
            templateModal = null;
        }

        function starterTemplates() {
            return [
                {
                    source: 'starter',
                    template_key: 'starter_issue_launch',
                    name: 'Issue Launch',
                    description: 'Hero image, teaser copy, CTA, and social links.',
                    composer_mode: 'block',
                    updated_at: 'Built in',
                    design: {
                        editor: 'grapesjs',
                        version: 1,
                        html: '<section style="padding:28px;background:#0d1b2a;color:#ffffff;font-family:Arial,sans-serif;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;"><tr><td valign="middle" width="55%" style="padding:10px 24px 10px 0;"><p style="margin:0 0 10px;color:#ff6a00;font-size:12px;font-weight:700;text-transform:uppercase;">New Issue</p><h1 style="margin:0 0 14px;font-size:36px;line-height:1.12;color:#ffffff;">The latest issue is here</h1><p style="margin:0 0 18px;font-size:16px;line-height:1.55;color:#dbeafe;">Feature your cover story, top articles, and a clear button to read the issue.</p><a href="https://example.com" style="display:inline-block;background:#ff6a00;color:#06111f;padding:12px 20px;border-radius:6px;text-decoration:none;font-weight:700;">Read the Issue</a></td><td valign="middle" width="45%" style="padding:10px 0 10px 10px;"><img src="https://via.placeholder.com/420x280?text=Cover" alt="" style="display:block;width:100%;max-width:420px;height:auto;border:0;border-radius:8px;margin:0 auto;"></td></tr></table></section><div style="padding:20px;font-family:Arial,sans-serif;"><h2 style="margin:0 0 8px;color:#0d1b2a;">Inside this issue</h2><p style="margin:0;color:#334155;line-height:1.55;">Add a short summary of the strongest stories and advertiser value.</p></div>',
                        css: '',
                        components: [],
                        styles: []
                    }
                },
                {
                    source: 'starter',
                    template_key: 'starter_advertiser_spotlight',
                    name: 'Advertiser Spotlight',
                    description: 'Sponsored feature format with image, copy, and button.',
                    composer_mode: 'block',
                    updated_at: 'Built in',
                    design: {
                        editor: 'grapesjs',
                        version: 1,
                        html: '<section style="padding:30px;background:#f8fafc;font-family:Arial,sans-serif;"><p style="margin:0 0 8px;color:#ff6a00;font-size:12px;font-weight:700;text-transform:uppercase;">Partner Content</p><h1 style="margin:0 0 12px;color:#0d1b2a;font-size:34px;line-height:1.12;">Advertiser spotlight headline</h1><img src="https://via.placeholder.com/640x280?text=Partner+Image" alt="" style="display:block;width:100%;height:auto;border:0;border-radius:8px;margin:16px 0;"><p style="margin:0 0 18px;color:#334155;font-size:16px;line-height:1.6;">Use this as a starting point for partner content, advertorial teasers, or sponsored newsletter placements.</p><a href="https://example.com" style="display:inline-block;background:#0077ff;color:#ffffff;padding:13px 22px;border-radius:6px;text-decoration:none;font-weight:700;">Learn More</a></section>',
                        css: '',
                        components: [],
                        styles: []
                    }
                },
                {
                    source: 'starter',
                    template_key: 'starter_news_digest',
                    name: 'News Digest',
                    description: 'Simple editorial digest with multiple story cards.',
                    composer_mode: 'block',
                    updated_at: 'Built in',
                    design: {
                        editor: 'grapesjs',
                        version: 1,
                        html: '<section style="padding:28px;background:#ffffff;font-family:Arial,sans-serif;"><h1 style="margin:0 0 16px;color:#0d1b2a;font-size:34px;">Weekly news digest</h1><article style="padding:16px 0;border-top:1px solid #cbd5e1;"><h2 style="margin:0 0 8px;color:#0d1b2a;font-size:22px;">Story headline one</h2><p style="margin:0;color:#334155;line-height:1.55;">A concise summary that makes the click obvious.</p></article><article style="padding:16px 0;border-top:1px solid #cbd5e1;"><h2 style="margin:0 0 8px;color:#0d1b2a;font-size:22px;">Story headline two</h2><p style="margin:0;color:#334155;line-height:1.55;">Another digest item for your audience.</p></article><div style="padding:18px 0;text-align:center;"><a href="https://example.com" style="display:inline-block;background:#ff6a00;color:#06111f;padding:12px 20px;border-radius:6px;text-decoration:none;font-weight:700;">See More News</a></div></section>',
                        css: '',
                        components: [],
                        styles: []
                    }
                }
            ];
        }

        function templateCard(item, allowDelete) {
            var deleteButton = allowDelete ? '<button type="button" class="danger" data-apcampaign-delete-template="' + escapeHtml(item.template_key) + '">Delete</button>' : '';
            var html = ((item.design || {}).html || item.rendered_html || item.raw_html || '<p>No preview available.</p>');
            return '<article class="apcampaign-template-card"><div class="apcampaign-template-preview" aria-hidden="true"><iframe sandbox="" tabindex="-1" srcdoc="' + escapeHtml(emailDocument(html, (item.design || {}).css || '')) + '"></iframe></div><div><strong>' + escapeHtml(item.name || 'Untitled') + '</strong><p>' + escapeHtml(item.description || '') + '</p><small>' + escapeHtml(item.composer_mode || 'block') + ' · ' + escapeHtml(item.updated_at || '') + '</small></div><div class="apcampaign-template-actions"><button type="button" data-apcampaign-use-template="' + escapeHtml(item.template_key) + '">Use Design</button>' + deleteButton + '</div></article>';
        }

        function renderTemplateModal(data) {
            closeTemplateModal();
            var isRawLibrary = modeField.value === 'raw_html';
            var samples = isRawLibrary ? [] : starterTemplates();
            var templates = data.templates || [];
            var sentDesigns = data.sent_designs || [];
            templatesByKey = {};
            samples.concat(templates, sentDesigns).forEach(function (template) {
                templatesByKey[template.template_key] = template;
            });
            templateModal = document.createElement('div');
            templateModal.className = 'apcampaign-modal';
            templateModal.setAttribute('role', 'dialog');
            templateModal.setAttribute('aria-modal', 'true');
            var starterSection = isRawLibrary ? '' : '<section><h3>Starter Samples</h3><div class="apcampaign-template-grid">' + samples.map(function (item) { return templateCard(item, false); }).join('') + '</div></section>';
            var savedTitle = isRawLibrary ? 'Saved Raw HTML Templates' : 'Saved Drag & Drop Templates';
            var sentTitle = isRawLibrary ? 'Previous Sent Raw HTML' : 'Previous Sent Drag & Drop Designs';
            templateModal.innerHTML = '<div class="apcampaign-modal-card"><header><div><p>' + (isRawLibrary ? 'Raw HTML Library' : 'Design Library') + '</p><h2>Templates and Starting Points</h2></div><button type="button" data-apcampaign-close-modal>×</button></header>' + starterSection + '<section><h3>' + savedTitle + '</h3><div class="apcampaign-template-grid">' + (templates.length ? templates.map(function (item) { return templateCard(item, true); }).join('') : '<p class="apcampaign-muted">No saved templates yet.</p>') + '</div></section><section><h3>' + sentTitle + '</h3><div class="apcampaign-template-grid">' + (sentDesigns.length ? sentDesigns.map(function (item) { return templateCard(item, false); }).join('') : '<p class="apcampaign-muted">No sent campaigns yet.</p>') + '</div></section></div>';
            document.body.appendChild(templateModal);
        }

        function addGrapesEmailBlocks(editor) {
            var blocks = editor.BlockManager;
            blocks.getCategories().reset();
            blocks.add('apcampaign-heading', {
                label: 'Heading',
                content: '<h1 style="margin:0;padding:18px 0;font-family:Arial,sans-serif;font-size:38px;line-height:1.16;color:#0d1b2a;">Email headline</h1>'
            });
            blocks.add('apcampaign-text', {
                label: 'Text',
                content: '<p style="margin:0;padding:12px 0;font-family:Arial,sans-serif;font-size:16px;line-height:1.55;color:#334155;">Write your message here.</p>'
            });
            blocks.add('apcampaign-image', {
                label: 'Image',
                content: '<div data-apcampaign-image-block="1" style="position:relative;padding:12px 0;text-align:center;"><img src="https://via.placeholder.com/640x320?text=Choose+Image" alt="" style="display:block;max-width:100%;height:auto;margin:0 auto;border:0;border-radius:6px;"><div data-apcampaign-media-button="1" style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);padding:10px 14px;background:rgba(13,27,42,.88);color:#ffffff;border-radius:6px;font-family:Arial,sans-serif;font-weight:700;font-size:14px;">Choose or upload image</div></div>'
            });
            blocks.add('apcampaign-button', {
                label: 'Button',
                content: '<div style="text-align:center;padding:16px 0;"><a href="https://example.com" style="display:inline-block;background:#0077ff;color:#ffffff;padding:14px 24px;border-radius:6px;text-decoration:none;font-family:Arial,sans-serif;font-weight:700;">Learn More</a></div>'
            });
            blocks.add('apcampaign-divider', {
                label: 'Divider',
                content: '<div style="padding:18px 0;"><hr style="border:0;border-top:1px solid #cbd5e1;width:100%;margin:0;"></div>'
            });
            blocks.add('apcampaign-spacer', {
                label: 'Spacer',
                content: '<div style="height:24px;"></div>'
            });
            blocks.add('apcampaign-hero', {
                label: 'Hero',
                content: '<section data-apcampaign-hero="1" style="position:relative;overflow:hidden;padding:44px 32px;min-height:260px;background-color:#0d1b2a;background-image:url(https://via.placeholder.com/1200x520?text=Hero+Background);background-size:cover;background-position:center;background-repeat:no-repeat;color:#ffffff;text-align:left;font-family:Arial,sans-serif;"><div data-apcampaign-hero-overlay="1" style="position:absolute;inset:0;background:#0d1b2a;opacity:.35;pointer-events:none;"></div><div style="position:relative;z-index:1;max-width:560px;"><p style="margin:0 0 10px;color:#ff6a00;font-size:12px;font-weight:700;text-transform:uppercase;">Featured</p><h1 style="margin:0 0 14px;font-size:38px;line-height:1.12;color:#ffffff;">Big campaign headline</h1><p style="margin:0;font-size:17px;line-height:1.55;color:#dbeafe;">Use this for a strong lead story, announcement, issue launch, or advertiser feature.</p></div></section>'
            });
            blocks.add('apcampaign-hero-image', {
                label: 'Hero + Image',
                content: '<section style="padding:28px;background:#0d1b2a;color:#ffffff;font-family:Arial,sans-serif;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;"><tr><td valign="middle" width="55%" style="padding:10px 24px 10px 0;"><p style="margin:0 0 10px;color:#ff6a00;font-size:12px;font-weight:700;text-transform:uppercase;">Featured</p><h1 style="margin:0 0 14px;font-size:36px;line-height:1.12;color:#ffffff;">Hero heading with image</h1><p style="margin:0 0 18px;font-size:16px;line-height:1.55;color:#dbeafe;">Lead with a strong headline, supporting copy, and a visual. Change this section background from the Block Background controls.</p><a href="https://example.com" style="display:inline-block;background:#ff6a00;color:#06111f;padding:12px 20px;border-radius:6px;text-decoration:none;font-weight:700;">Read More</a></td><td valign="middle" width="45%" style="padding:10px 0 10px 10px;"><div data-apcampaign-image-block="1" style="position:relative;text-align:center;"><img src="https://via.placeholder.com/420x280?text=Choose+Hero+Image" alt="" style="display:block;width:100%;max-width:420px;height:auto;border:0;border-radius:8px;margin:0 auto;"><div data-apcampaign-media-button="1" style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);padding:10px 14px;background:rgba(13,27,42,.88);color:#ffffff;border-radius:6px;font-family:Arial,sans-serif;font-weight:700;font-size:14px;">Choose image</div></div></td></tr></table></section>'
            });
            blocks.add('apcampaign-two-column', {
                label: 'Two Columns',
                content: '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-family:Arial,sans-serif;"><tr><td width="50%" valign="top" style="padding:16px;"><h2 style="margin:0 0 8px;color:#0d1b2a;font-size:22px;">Column One</h2><p style="margin:0;color:#334155;line-height:1.5;">Add supporting copy here.</p></td><td width="50%" valign="top" style="padding:16px;"><h2 style="margin:0 0 8px;color:#0d1b2a;font-size:22px;">Column Two</h2><p style="margin:0;color:#334155;line-height:1.5;">Add supporting copy here.</p></td></tr></table>'
            });
            blocks.add('apcampaign-card', {
                label: 'Card',
                content: '<article style="margin:16px 0;padding:20px;border:1px solid #cbd5e1;border-radius:8px;background:#ffffff;font-family:Arial,sans-serif;"><h2 style="margin:0 0 8px;color:#0d1b2a;font-size:24px;">Card headline</h2><p style="margin:0;color:#334155;line-height:1.55;">Use cards for grouped offers, event details, sponsor notes, or article promos.</p></article>'
            });
            blocks.add('apcampaign-quote', {
                label: 'Quote',
                content: '<blockquote style="margin:18px 0;padding:18px 22px;border-left:4px solid #ff6a00;background:#f8fafc;font-family:Arial,sans-serif;color:#334155;"><p style="margin:0 0 8px;font-size:20px;line-height:1.45;">“Pull quote or testimonial goes here.”</p><footer style="font-size:13px;color:#64748b;">Source name</footer></blockquote>'
            });
            blocks.add('apcampaign-list', {
                label: 'List',
                content: '<ul style="margin:14px 0;padding-left:24px;font-family:Arial,sans-serif;color:#334155;line-height:1.6;"><li>First important point</li><li>Second important point</li><li>Third important point</li></ul>'
            });
            blocks.add('apcampaign-teaser', {
                label: 'Article Teaser',
                content: '<table data-apcampaign-teaser="1" data-apcampaign-teaser-category="" data-apcampaign-teaser-source-url="" role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-family:Arial,sans-serif;margin:16px 0;"><tr><td width="180" valign="top" style="padding-right:16px;"><a data-apcampaign-teaser-link="1" href="https://example.com" style="text-decoration:none;"><img data-apcampaign-teaser-image="1" src="https://via.placeholder.com/180x120?text=Story" alt="" style="display:block;width:180px;max-width:100%;height:auto;border:0;border-radius:6px;"></a></td><td valign="top"><p data-apcampaign-teaser-label="1" style="margin:0 0 6px;color:#ff6a00;font-size:12px;font-weight:700;text-transform:uppercase;">Story</p><h2 style="margin:0 0 8px;color:#0d1b2a;font-size:22px;line-height:1.2;"><a data-apcampaign-teaser-link="1" data-apcampaign-teaser-title="1" href="https://example.com" style="color:#0d1b2a;text-decoration:none;">Article or issue teaser</a></h2><p data-apcampaign-teaser-excerpt="1" style="margin:0;color:#334155;line-height:1.5;">Short summary text that drives the reader to click through.</p></td></tr></table>'
            });
            blocks.add('apcampaign-social', {
                label: 'Social Links',
                content: '<div data-apcampaign-social-block="1" data-apcampaign-social-display="names" style="padding:18px 0;text-align:center;font-family:Arial,sans-serif;"><a data-apcampaign-social-key="facebook" data-apcampaign-social-label="Facebook" href="#" style="display:inline-flex;align-items:center;margin:4px 6px;color:#0077ff;text-decoration:none;font-weight:700;font-family:Arial,sans-serif;">Facebook</a><a data-apcampaign-social-key="instagram" data-apcampaign-social-label="Instagram" href="#" style="display:inline-flex;align-items:center;margin:4px 6px;color:#0077ff;text-decoration:none;font-weight:700;font-family:Arial,sans-serif;">Instagram</a><a data-apcampaign-social-key="linkedin" data-apcampaign-social-label="LinkedIn" href="#" style="display:inline-flex;align-items:center;margin:4px 6px;color:#0077ff;text-decoration:none;font-weight:700;font-family:Arial,sans-serif;">LinkedIn</a></div>'
            });
            var blockOrder = [
                'apcampaign-hero-image',
                'apcampaign-hero',
                'apcampaign-heading',
                'apcampaign-image',
                'apcampaign-text',
                'apcampaign-button',
                'apcampaign-teaser',
                'apcampaign-two-column',
                'apcampaign-card',
                'apcampaign-quote',
                'apcampaign-list',
                'apcampaign-social',
                'apcampaign-divider',
                'apcampaign-spacer'
            ];
            var allBlocks = blocks.getAll();
            allBlocks.reset(blockOrder.map(function (id) {
                return allBlocks.get(id);
            }).filter(Boolean));
        }

        function addGrapesBlockByLabel(label) {
            if (!grapesEditor || !label) {
                return;
            }
            var match = null;
            grapesEditor.BlockManager.getAll().forEach(function (block) {
                if (!match && String(block.get('label') || '').trim() === String(label).trim()) {
                    match = block;
                }
            });
            if (!match) {
                return;
            }
            var added = grapesEditor.addComponents(match.get('content') || '');
            var component = Array.isArray(added) ? added[0] : added;
            if (component) {
                grapesEditor.select(component);
                renderGrapesInspector(component);
            }
            syncGrapes();
        }

        function normalizeGrapesBlocks(container) {
            if (!container) {
                return;
            }
            qsa('.gjs-block', container).forEach(function (block) {
                block.style.setProperty('float', 'none', 'important');
                block.style.setProperty('clear', 'both', 'important');
                block.style.setProperty('display', 'flex', 'important');
                block.style.setProperty('align-items', 'center', 'important');
                block.style.setProperty('justify-content', 'center', 'important');
                block.style.setProperty('box-sizing', 'border-box', 'important');
                block.style.setProperty('width', '100%', 'important');
                block.style.setProperty('max-width', '100%', 'important');
                block.style.setProperty('min-height', '46px', 'important');
                block.style.setProperty('padding', '9px 12px', 'important');
                block.style.setProperty('margin', '0 0 8px', 'important');
                block.style.setProperty('border-radius', '6px', 'important');
                block.style.setProperty('font-family', 'inherit', 'important');
                block.style.setProperty('font-size', '13px', 'important');
                block.style.setProperty('font-weight', '700', 'important');
                block.style.setProperty('line-height', '1.2', 'important');
                block.style.setProperty('text-align', 'center', 'important');
                var label = qs('.gjs-block-label', block);
                if (label) {
                    label.style.setProperty('width', '100%', 'important');
                    label.style.setProperty('padding', '0', 'important');
                    label.style.setProperty('font-family', 'inherit', 'important');
                    label.style.setProperty('font-size', '13px', 'important');
                    label.style.setProperty('line-height', '1.2', 'important');
                    label.style.setProperty('text-align', 'center', 'important');
                }
            });
        }

        function initGrapes() {
            if (!useGrapes || grapesEditor || !window.grapesjs) {
                return;
            }
            form.classList.add('is-grapes');
            canvas.innerHTML = '<div class="apcampaign-grapes-shell" data-apcampaign-grapes></div>';
            grapesHost = qs('[data-apcampaign-grapes]', canvas);
            grapesEditor = window.grapesjs.init({
                container: grapesHost,
                height: '100%',
                storageManager: false,
                fromElement: false,
                panels: {defaults: []},
                selectorManager: {componentFirst: true},
                blockManager: {appendTo: qs('[data-apcampaign-blocks]', form) || undefined},
                styleManager: {
                    sectors: [
                        {name: 'Block Background', open: true, properties: ['background-color']},
                        {name: 'Layout', open: true, properties: ['width', 'height', 'min-height', 'padding', 'margin', 'display', 'text-align']},
                        {name: 'Typography', open: true, properties: ['font-family', 'font-size', 'font-weight', 'line-height', 'color']},
                        {name: 'Decorations', open: false, properties: ['border-radius', 'border', 'box-shadow']},
                        {name: 'Image', open: false, properties: ['object-fit']}
                    ]
                }
            });
            window.apCampaignGrapesEditor = grapesEditor;
            addGrapesEmailBlocks(grapesEditor);
            var grapesBlocksContainer = qs('[data-apcampaign-blocks]', form);
            if (grapesBlocksContainer) {
                normalizeGrapesBlocks(grapesBlocksContainer);
                if (window.MutationObserver) {
                    new MutationObserver(function () {
                        normalizeGrapesBlocks(grapesBlocksContainer);
                    }).observe(grapesBlocksContainer, {childList: true, subtree: true});
                }
                window.setTimeout(function () { normalizeGrapesBlocks(grapesBlocksContainer); }, 100);
                window.setTimeout(function () { normalizeGrapesBlocks(grapesBlocksContainer); }, 700);
                grapesBlocksContainer.addEventListener('click', function (event) {
                    var blockNode = event.target.closest('.gjs-block');
                    if (!blockNode || !useGrapes) {
                        return;
                    }
                    addGrapesBlockByLabel(blockNode.getAttribute('title') || blockNode.textContent || '');
                });
            }
            grapesEditor.Commands.add('apcampaign-open-media', {
                run: function (editor) {
                    var selected = editor.getSelected();
                    if (!selected) {
                        return;
                    }
                    openImagePickerForComponent(selected);
                }
            });
            loadGrapesDesign(design && design.editor === 'grapesjs' ? design : {});
            grapesEditor.on('component:selected', function (component) {
                if (!component) {
                    renderGrapesInspector(null);
                    return;
                }
                renderGrapesInspector(component);
                var hasImage = componentHasImage(component);
                if (!hasImage) {
                    return;
                }
                var toolbar = component.get('toolbar') || [];
                var hasMedia = toolbar.some(function (item) {
                    return item.command === 'apcampaign-open-media';
                });
                if (!hasMedia) {
                    toolbar.unshift({attributes: {title: 'Choose image'}, label: 'Media', command: 'apcampaign-open-media'});
                    component.set('toolbar', toolbar);
                }
            });
            grapesEditor.on('load', function () {
                applyGrapesCanvasTheme();
                grapesEditor.select(null);
                window.setTimeout(function () {
                    grapesEditor.select(null);
                }, 50);
                var canvasDoc = grapesEditor.Canvas.getDocument();
                if (!canvasDoc) {
                    return;
                }
                canvasDoc.addEventListener('click', function (event) {
                    var target = event.target && event.target.closest ? event.target.closest('[data-apcampaign-media-button]') : null;
                    if (!target) {
                        return;
                    }
                    event.preventDefault();
                    event.stopPropagation();
                    var component = grapesEditor.getSelected();
                    if (!component || !componentHasImage(component)) {
                        var wrapper = target.closest('[data-apcampaign-image-block]');
                        if (wrapper) {
                            var domBlocks = Array.prototype.slice.call(canvasDoc.querySelectorAll('[data-apcampaign-image-block]'));
                            var componentBlocks = grapesEditor.getWrapper().find('[data-apcampaign-image-block]');
                            var blockIndex = domBlocks.indexOf(wrapper);
                            if (blockIndex >= 0 && componentBlocks[blockIndex]) {
                                grapesEditor.select(componentBlocks[blockIndex]);
                                openImagePickerForComponent(componentBlocks[blockIndex]);
                                return;
                            }
                        }
                    }
                    grapesEditor.runCommand('apcampaign-open-media');
                }, true);
            });
            grapesEditor.on('update', function () {
                syncGrapes();
                dirty = true;
                updateCommandState();
            });
            document.addEventListener('apcampaign:themechange', applyGrapesCanvasTheme);
            if (resultBox) {
                renderGrapesInspector(null);
            }
            setTimeout(applyGrapesCanvasTheme, 100);
            syncGrapes();
        }

        function blockLabel(type) {
            return String(type || 'block').replace(/_/g, ' ').replace(/\b\w/g, function (letter) {
                return letter.toUpperCase();
            });
        }

        function paint() {
            if (useGrapes) {
                syncGrapes();
                updateCommandState();
                return;
            }
            sync();
            if (!design.blocks.length) {
                canvas.innerHTML = '<div class="apcampaign-empty apcampaign-drop-zone">Drag blocks here to build your email.</div>';
                renderInspector();
                updateCommandState();
                return;
            }
            canvas.innerHTML = design.blocks.map(function (block, index) {
                return '<article class="apcampaign-block' + (index === selectedIndex ? ' selected' : '') + '" data-block-index="' + index + '" tabindex="0" draggable="true"><div class="apcampaign-block-toolbar"><span class="apcampaign-drag-handle" title="Drag to reorder">Drag</span><button type="button" data-move-up title="Move block up">Up</button><button type="button" data-move-down title="Move block down">Down</button><button type="button" data-duplicate>Copy</button><button type="button" data-delete>Delete</button></div><div class="apcampaign-block-label">' + escapeHtml(blockLabel(block.type)) + '</div><div class="apcampaign-block-preview">' + renderEmailBlock(block.type, block.options, true) + '</div></article>';
            }).join('');
            renderInspector();
            updateCommandState();
        }

        function collectHtml() {
            if (modeField.value === 'raw_html') {
                return raw.value;
            }
            if (useGrapes) {
                syncGrapes();
                return renderedField ? renderedField.value : emailDocument(design.html || '', design.css || '');
            }
            return design.blocks.map(function (block) {
                return renderEmailBlock(block.type, block.options);
            }).join('');
        }

        function switchMode(mode) {
            modeField.value = mode;
            canvas.classList.toggle('hidden', mode !== 'block');
            raw.classList.toggle('hidden', mode !== 'raw_html');
            qsa('[data-apcampaign-mode]', form).forEach(function (button) {
                button.classList.toggle('active', button.getAttribute('data-apcampaign-mode') === mode);
            });
            updateRawMeta();
        }

        function updateRawMeta() {
            if (rawCharCount) {
                rawCharCount.textContent = String((raw.value || '').length) + ' chars';
            }
            if (modeField.value === 'raw_html' && resultBox) {
                var existingFrame = qs('iframe', resultBox);
                if (!existingFrame) {
                    resultBox.innerHTML = '<iframe sandbox="" title="Live HTML preview"></iframe><p class="apcampaign-raw-hint">Click Predict to get AI performance predictions, or Spam to check deliverability.</p>';
                    existingFrame = qs('iframe', resultBox);
                }
                existingFrame.srcdoc = raw.value || '<!doctype html><html><body style="font-family:Arial,sans-serif;color:#555;padding:24px;">Live preview appears here.</body></html>';
            }
        }

        function selectBlock(index) {
            if (index < 0 || index >= design.blocks.length) {
                selectedIndex = -1;
            } else {
                selectedIndex = index;
            }
            paint();
            if (selectedIndex >= 0) {
                var selectedBlock = qs('[data-block-index="' + selectedIndex + '"]', canvas);
                if (selectedBlock) {
                    selectedBlock.focus();
                }
            }
        }

        function renderInspector() {
            if (modeField.value === 'raw_html') {
                return;
            }
            if (!resultBox) {
                return;
            }
            if (selectedIndex < 0 || !design.blocks[selectedIndex]) {
                resultBox.innerHTML = '<div class="apcampaign-no-selection"><strong>Select a block</strong><p>Drag blocks from the left, then click a block to edit its settings here.</p></div><div class="apcampaign-tip green"><strong>Subject Line Tips:</strong> Keep it focused and avoid excessive punctuation.</div><div class="apcampaign-tip amber"><strong>Best Send Times:</strong> B2B audiences often perform best Tuesday through Thursday.</div>';
                return;
            }
            var block = design.blocks[selectedIndex];
            resultBox.innerHTML = '<div class="apcampaign-property-editor"><div class="apcampaign-property-head"><strong>Edit Block</strong><span>' + escapeHtml(blockLabel(block.type)) + '</span></div>' + fieldFor(block.type, block.options || {}) + '<div class="apcampaign-property-actions"><button type="button" data-inspector-duplicate>Duplicate</button><button type="button" data-inspector-delete>Delete</button></div><p class="apcampaign-drag-note">Drag the selected block in the canvas to reorder it.</p></div>';
        }

        function insertBlockAt(type, index) {
            if (useGrapes) {
                return;
            }
            recordHistory();
            var block = {id: String(Date.now()) + '-' + Math.random().toString(16).slice(2), type: type, options: defaultOptions(type)};
            var target = Math.max(0, Math.min(index, design.blocks.length));
            design.blocks.splice(target, 0, block);
            selectedIndex = target;
            paint();
        }

        function getDropIndex(y) {
            var blocks = qsa('.apcampaign-block:not(.dragging)', canvas);
            for (var i = 0; i < blocks.length; i += 1) {
                var box = blocks[i].getBoundingClientRect();
                if (y < box.top + box.height / 2) {
                    return parseInt(blocks[i].getAttribute('data-block-index'), 10);
                }
            }
            return design.blocks.length;
        }

        function showDropIndicator(index) {
            if (!dropIndicator) {
                dropIndicator = document.createElement('div');
                dropIndicator.className = 'apcampaign-drop-indicator';
            }
            var block = qs('[data-block-index="' + index + '"]', canvas);
            if (block) {
                canvas.insertBefore(dropIndicator, block);
            } else {
                canvas.appendChild(dropIndicator);
            }
        }

        function clearDropIndicator() {
            if (dropIndicator && dropIndicator.parentNode) {
                dropIndicator.parentNode.removeChild(dropIndicator);
            }
        }

        function moveBlock(fromIndex, toIndex) {
            if (fromIndex === null || fromIndex < 0 || fromIndex >= design.blocks.length) {
                return;
            }
            recordHistory();
            var block = design.blocks.splice(fromIndex, 1)[0];
            var target = toIndex;
            if (fromIndex < target) {
                target -= 1;
            }
            target = Math.max(0, Math.min(target, design.blocks.length));
            design.blocks.splice(target, 0, block);
            selectedIndex = target;
            paint();
        }

        function runBlockCommand(command, index) {
            if (index < 0 || index >= design.blocks.length) {
                return false;
            }
            if (command === 'delete') {
                recordHistory();
                design.blocks.splice(index, 1);
                selectedIndex = -1;
                paint();
                return true;
            }
            if (command === 'duplicate') {
                recordHistory();
                var clone = JSON.parse(JSON.stringify(design.blocks[index]));
                clone.id = String(Date.now()) + '-' + Math.random().toString(16).slice(2);
                design.blocks.splice(index + 1, 0, clone);
                selectedIndex = index + 1;
                paint();
                return true;
            }
            if (command === 'move-up') {
                moveBlock(index, index - 1);
                return true;
            }
            if (command === 'move-down') {
                moveBlock(index, index + 2);
                return true;
            }
            return false;
        }

        function pointIsInsideCanvas(x, y) {
            var box = canvas.getBoundingClientRect();
            return x >= box.left && x <= box.right && y >= box.top && y <= box.bottom;
        }

        function beginPointerDrag(event, payload) {
            if (useGrapes) {
                return;
            }
            if (typeof event.button === 'number' && event.button !== 0) {
                return;
            }
            pointerDrag = {
                type: payload.type || '',
                index: typeof payload.index === 'number' ? payload.index : null,
                startX: event.clientX,
                startY: event.clientY,
                moved: false,
                source: payload.source || null
            };
        }

        function handlePointerDragMove(event) {
            if (useGrapes) {
                return;
            }
            if (!pointerDrag) {
                return;
            }
            var dx = Math.abs(event.clientX - pointerDrag.startX);
            var dy = Math.abs(event.clientY - pointerDrag.startY);
            if (dx < 4 && dy < 4 && !pointerDrag.moved) {
                return;
            }
            pointerDrag.moved = true;
            if (pointerDrag.source) {
                pointerDrag.source.classList.add('dragging');
            }
            if (pointIsInsideCanvas(event.clientX, event.clientY)) {
                showDropIndicator(getDropIndex(event.clientY));
            } else {
                clearDropIndicator();
            }
        }

        function handlePointerDragEnd(event) {
            if (useGrapes) {
                return;
            }
            if (!pointerDrag) {
                return;
            }
            var session = pointerDrag;
            pointerDrag = null;
            clearDropIndicator();
            qsa('.apcampaign-block.dragging, .apcampaign-block-tile.dragging', form).forEach(function (item) {
                item.classList.remove('dragging');
            });
            if (!session.moved || !pointIsInsideCanvas(event.clientX, event.clientY)) {
                return;
            }
            var index = getDropIndex(event.clientY);
            if (session.type) {
                insertBlockAt(session.type, index);
                return;
            }
            moveBlock(session.index, index);
        }

        document.addEventListener('mousemove', handlePointerDragMove);
        document.addEventListener('mouseup', handlePointerDragEnd);
        document.addEventListener('pointermove', handlePointerDragMove);
        document.addEventListener('pointerup', handlePointerDragEnd);

        qsa('[data-apcampaign-add-block]', form).forEach(function (button) {
            button.setAttribute('draggable', 'true');
            button.addEventListener('mousedown', function (event) {
                beginPointerDrag(event, {
                    type: button.getAttribute('data-apcampaign-add-block'),
                    source: button
                });
            });
            button.addEventListener('pointerdown', function (event) {
                beginPointerDrag(event, {
                    type: button.getAttribute('data-apcampaign-add-block'),
                    source: button
                });
            });
            button.addEventListener('dragstart', function (event) {
                draggedType = button.getAttribute('data-apcampaign-add-block');
                draggedIndex = null;
                event.dataTransfer.effectAllowed = 'copy';
                event.dataTransfer.setData('application/x-apcampaign-block', draggedType);
                event.dataTransfer.setData('text/plain', draggedType);
            });
            button.addEventListener('click', function () {
                if (useGrapes) {
                    return;
                }
                var type = button.getAttribute('data-apcampaign-add-block');
                insertBlockAt(type, design.blocks.length);
            });
        });

        function updateInspectorControl(event) {
            if (event.target.closest('[data-apcampaign-settings-form]')) {
                return;
            }
            var option = event.target.getAttribute('data-option');
            if (selectedIndex < 0 || !option || !design.blocks[selectedIndex]) {
                return;
            }
            design.blocks[selectedIndex].options[option] = event.target.value;
            if (event.target.type === 'range') {
                var output = event.target.parentNode ? qs('output', event.target.parentNode) : null;
                if (output) {
                    var suffix = output.textContent.replace(/^-?[0-9.]+/, '');
                    output.textContent = event.target.value + suffix;
                }
            }
            if (event.target.type === 'color' || event.target.type === 'text') {
                qsa('[data-option="' + option + '"]', resultBox).forEach(function (paired) {
                    if (paired !== event.target && (paired.type === 'color' || paired.type === 'text')) {
                        paired.value = event.target.value;
                    }
                });
            }
            sync();
            var blockNode = qs('[data-block-index="' + selectedIndex + '"]', canvas);
            if (blockNode) {
                qs('.apcampaign-block-preview', blockNode).innerHTML = renderEmailBlock(design.blocks[selectedIndex].type, design.blocks[selectedIndex].options, true);
            }
        }

        resultBox.addEventListener('input', updateInspectorControl);
        resultBox.addEventListener('change', updateInspectorControl);
        resultBox.addEventListener('input', function (event) {
            if (event.target.matches('[data-grapes-style],[data-grapes-style-px],[data-grapes-attr],[data-grapes-image-attr],[data-grapes-content],[data-grapes-hero-bg],[data-grapes-hero-opacity],[data-grapes-bg],[data-grapes-bg-overlay],[data-grapes-social-display],[data-grapes-social-url],[data-grapes-social-label],[data-apcampaign-teaser-field],[data-apcampaign-teaser-external],[data-apcampaign-teaser-category],[data-apcampaign-teaser-post]')) {
                applyGrapesInspectorInput(event.target);
            }
        });
        resultBox.addEventListener('change', function (event) {
            if (event.target.matches('[data-grapes-style],[data-grapes-style-px],[data-grapes-attr],[data-grapes-image-attr],[data-grapes-content],[data-grapes-hero-bg],[data-grapes-hero-opacity],[data-grapes-bg],[data-grapes-bg-overlay],[data-grapes-social-display],[data-grapes-social-url],[data-grapes-social-label],[data-apcampaign-teaser-field],[data-apcampaign-teaser-external],[data-apcampaign-teaser-category],[data-apcampaign-teaser-post]')) {
                applyGrapesInspectorInput(event.target);
            }
        });
        resultBox.addEventListener('focusin', function (event) {
            if (!event.target.matches('[data-option]') || event.target.closest('[data-apcampaign-settings-form]')) {
                return;
            }
            if (!inspectorEditActive) {
                recordHistory();
                inspectorEditActive = true;
            }
        });
        resultBox.addEventListener('focusout', function () {
            inspectorEditActive = false;
        });

        resultBox.addEventListener('click', function (event) {
            if (event.target.matches('[data-apcampaign-open-templates]')) {
                loadTemplates();
                return;
            }
            if (event.target.matches('[data-apcampaign-use-template]')) {
                var template = templatesByKey[event.target.getAttribute('data-apcampaign-use-template') || ''];
                applyTemplate(template);
                return;
            }
            if (event.target.matches('[data-apcampaign-save-settings]')) {
                var settingsForm = event.target.closest('[data-apcampaign-settings-form]');
                if (!settingsForm) {
                    return;
                }
                var fd = new FormData();
                qsa('[data-option]', settingsForm).forEach(function (input) {
                    fd.append(input.getAttribute('data-option'), input.type === 'checkbox' ? (input.checked ? '1' : '0') : input.value);
                });
                fd.append('action', 'apcampaign_app_save_settings');
                fd.append('nonce', apCampaignApp.nonce);
                post(fd).then(function (data) {
                    renderSettings(data.settings || {});
                }).catch(showError);
                return;
            }
            if (event.target.matches('[data-apcampaign-grapes-media]')) {
                if (grapesEditor) {
                    grapesEditor.runCommand('apcampaign-open-media');
                }
                return;
            }
            if (event.target.matches('[data-apcampaign-grapes-hero-media]')) {
                if (!grapesEditor || !containingHeroComponent(grapesEditor.getSelected())) {
                    return;
                }
                openCampaignMediaPicker({
                    title: 'Choose hero background',
                    buttonText: 'Use this image',
                    onSelect: function (attachment) {
                        var selectedHero = containingHeroComponent(grapesEditor.getSelected());
                        setHeroBackgroundImage(selectedHero, attachment.url || '');
                        renderGrapesInspector(grapesEditor.getSelected());
                        syncGrapes();
                    }
                });
                return;
            }
            if (event.target.matches('[data-apcampaign-grapes-bg-media]')) {
                if (!grapesEditor || !grapesEditor.getSelected()) {
                    return;
                }
                openCampaignMediaPicker({
                    title: 'Choose block background',
                    buttonText: 'Use this image',
                    onSelect: function (attachment) {
                        var selectedBlock = grapesEditor.getSelected();
                        setBlockBackgroundImage(selectedBlock, attachment.url || '', backgroundOverlay(selectedBlock));
                        renderGrapesInspector(selectedBlock);
                        syncGrapes();
                    }
                });
                return;
            }
            if (event.target.matches('[data-apcampaign-teaser-load]')) {
                if (!grapesEditor || !grapesEditor.getSelected()) {
                    return;
                }
                var selectedTeaserForLoad = containingTeaserComponent(grapesEditor.getSelected());
                var categorySelect = qs('[data-apcampaign-teaser-category]', resultBox);
                if (selectedTeaserForLoad) {
                    loadTeaserPosts(selectedTeaserForLoad, categorySelect ? categorySelect.value : '');
                }
                return;
            }
            if (event.target.matches('[data-apcampaign-teaser-fetch]')) {
                if (!grapesEditor || !grapesEditor.getSelected()) {
                    return;
                }
                var selectedTeaserForFetch = containingTeaserComponent(grapesEditor.getSelected());
                var sourceInput = qs('[data-apcampaign-teaser-external]', resultBox);
                if (selectedTeaserForFetch && sourceInput) {
                    fetchExternalTeaser(selectedTeaserForFetch, sourceInput.value);
                }
                return;
            }
            if (event.target.matches('[data-apcampaign-teaser-image]')) {
                if (!grapesEditor || !grapesEditor.getSelected()) {
                    return;
                }
                var selectedTeaserForImage = containingTeaserComponent(grapesEditor.getSelected());
                if (!selectedTeaserForImage) {
                    return;
                }
                openCampaignMediaPicker({
                    title: 'Choose teaser image',
                    buttonText: 'Use this image',
                    onSelect: function (attachment) {
                        setTeaserData(selectedTeaserForImage, {
                            image: attachment.url || '',
                            alt: attachment.alt || attachment.title || teaserData(selectedTeaserForImage).title || ''
                        });
                        grapesEditor.select(selectedTeaserForImage);
                        renderGrapesInspector(selectedTeaserForImage);
                        syncGrapes();
                    }
                });
                return;
            }
            if (event.target.matches('[data-apcampaign-grapes-duplicate]')) {
                if (grapesEditor && grapesEditor.getSelected()) {
                    var selected = grapesEditor.getSelected();
                    var clone = selected.clone();
                    selected.parent().append(clone);
                    grapesEditor.select(clone);
                    syncGrapes();
                }
                return;
            }
            if (event.target.matches('[data-apcampaign-grapes-delete]')) {
                if (grapesEditor && grapesEditor.getSelected() && window.confirm('Delete this block?')) {
                    grapesEditor.getSelected().remove();
                    renderGrapesInspector(null);
                    syncGrapes();
                }
                return;
            }
            if (selectedIndex < 0 || !design.blocks[selectedIndex]) {
                return;
            }
            if (event.target.matches('[data-inspector-delete]')) {
                recordHistory();
                design.blocks.splice(selectedIndex, 1);
                selectedIndex = -1;
                paint();
            }
            if (event.target.matches('[data-inspector-duplicate]')) {
                recordHistory();
                var clone = JSON.parse(JSON.stringify(design.blocks[selectedIndex]));
                clone.id = String(Date.now()) + '-' + Math.random().toString(16).slice(2);
                design.blocks.splice(selectedIndex + 1, 0, clone);
                selectedIndex += 1;
                paint();
            }
        });

        canvas.addEventListener('click', function (event) {
            if (useGrapes) {
                return;
            }
            var blockNode = event.target.closest('[data-block-index]');
            if (!blockNode) {
                return;
            }
            var index = parseInt(blockNode.getAttribute('data-block-index'), 10);
            var inlineTarget = event.target.closest('[data-inline-option]');
            if (inlineTarget) {
                if (inlineTarget.closest('a')) {
                    event.preventDefault();
                }
                selectedIndex = index;
                renderInspector();
                updateCommandState();
                return;
            }
            if (toolbarCommandHandled) {
                toolbarCommandHandled = false;
                return;
            }
            if (event.target.closest('[data-delete]')) {
                event.preventDefault();
                event.stopPropagation();
                runBlockCommand('delete', index);
                return;
            }
            if (event.target.closest('[data-duplicate]')) {
                event.preventDefault();
                event.stopPropagation();
                runBlockCommand('duplicate', index);
                return;
            }
            if (event.target.closest('[data-move-up]')) {
                event.preventDefault();
                event.stopPropagation();
                runBlockCommand('move-up', index);
                return;
            }
            if (event.target.closest('[data-move-down]')) {
                event.preventDefault();
                event.stopPropagation();
                runBlockCommand('move-down', index);
                return;
            }
            beginPointerDrag(event, {index: index, source: blockNode});
            selectBlock(index);
        });

        canvas.addEventListener('focusin', function (event) {
            if (useGrapes) {
                return;
            }
            var inline = event.target.closest('[data-inline-option]');
            if (!inline) {
                return;
            }
            var blockNode = inline.closest('[data-block-index]');
            if (!blockNode) {
                return;
            }
            selectedIndex = parseInt(blockNode.getAttribute('data-block-index'), 10);
            if (!inlineEditActive) {
                recordHistory();
                inlineEditActive = true;
            }
        });

        canvas.addEventListener('focusout', function () {
            if (useGrapes) {
                return;
            }
            inlineEditActive = false;
        });

        canvas.addEventListener('input', function (event) {
            if (useGrapes) {
                return;
            }
            var inline = event.target.closest('[data-inline-option]');
            if (!inline) {
                return;
            }
            var blockNode = inline.closest('[data-block-index]');
            if (!blockNode) {
                return;
            }
            var index = parseInt(blockNode.getAttribute('data-block-index'), 10);
            var optionKey = inline.getAttribute('data-inline-option');
            if (!design.blocks[index] || !optionKey) {
                return;
            }
            selectedIndex = index;
            design.blocks[index].options[optionKey] = inline.innerText;
            dirty = true;
            sync();
            updateCommandState();
            renderInspector();
        });

        canvas.addEventListener('pointerdown', function (event) {
            if (useGrapes) {
                return;
            }
            var toolbarButton = event.target.closest('[data-delete],[data-duplicate],[data-move-up],[data-move-down]');
            if (toolbarButton) {
                var commandBlock = toolbarButton.closest('[data-block-index]');
                if (commandBlock) {
                    var commandIndex = parseInt(commandBlock.getAttribute('data-block-index'), 10);
                    var command = toolbarButton.matches('[data-delete]') ? 'delete' : (toolbarButton.matches('[data-duplicate]') ? 'duplicate' : (toolbarButton.matches('[data-move-up]') ? 'move-up' : 'move-down'));
                    event.preventDefault();
                    event.stopPropagation();
                    toolbarCommandHandled = runBlockCommand(command, commandIndex);
                }
                return;
            }
            if (event.target.closest('button,input,textarea,select,a,[data-inline-option]')) {
                return;
            }
            var blockNode = event.target.closest('[data-block-index]');
            if (!blockNode) {
                return;
            }
            beginPointerDrag(event, {
                index: parseInt(blockNode.getAttribute('data-block-index'), 10),
                source: blockNode
            });
        });

        canvas.addEventListener('dragstart', function (event) {
            if (useGrapes) {
                return;
            }
            var blockNode = event.target.closest('[data-block-index]');
            if (!blockNode) {
                return;
            }
            draggedIndex = parseInt(blockNode.getAttribute('data-block-index'), 10);
            draggedType = '';
            selectedIndex = draggedIndex;
            blockNode.classList.add('dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('application/x-apcampaign-reorder', String(draggedIndex));
            event.dataTransfer.setData('text/plain', 'reorder');
        });

        canvas.addEventListener('dragend', function () {
            if (useGrapes) {
                return;
            }
            clearDropIndicator();
            qsa('.apcampaign-block.dragging', canvas).forEach(function (block) {
                block.classList.remove('dragging');
            });
            draggedIndex = null;
            draggedType = '';
        });

        canvas.addEventListener('dragover', function (event) {
            if (useGrapes) {
                return;
            }
            event.preventDefault();
            var index = getDropIndex(event.clientY);
            showDropIndicator(index);
            event.dataTransfer.dropEffect = draggedType ? 'copy' : 'move';
        });

        canvas.addEventListener('dragleave', function (event) {
            if (useGrapes) {
                return;
            }
            if (!canvas.contains(event.relatedTarget)) {
                clearDropIndicator();
            }
        });

        canvas.addEventListener('drop', function (event) {
            if (useGrapes) {
                return;
            }
            event.preventDefault();
            var index = getDropIndex(event.clientY);
            var type = event.dataTransfer.getData('application/x-apcampaign-block') || draggedType;
            clearDropIndicator();
            if (type) {
                insertBlockAt(type, index);
                return;
            }
            moveBlock(draggedIndex, index);
        });

        qsa('[data-apcampaign-mode]', form).forEach(function (button) {
            button.addEventListener('click', function () {
                switchMode(button.getAttribute('data-apcampaign-mode'));
            });
        });

        qs('[data-apcampaign-preview]', form).addEventListener('click', function () {
            var html = previewTabbedDocument(collectHtml());
            var popupWidth = 850;
            var popupHeight = 820;
            var openerLeft = typeof window.screenX === 'number' ? window.screenX : window.screenLeft || 0;
            var openerTop = typeof window.screenY === 'number' ? window.screenY : window.screenTop || 0;
            var openerWidth = window.outerWidth || window.innerWidth || window.screen.width;
            var openerHeight = window.outerHeight || window.innerHeight || window.screen.height;
            var left = Math.max(20, Math.round(openerLeft + ((openerWidth - popupWidth) / 2)));
            var top = Math.max(20, Math.round(openerTop + ((openerHeight - popupHeight) / 2)));
            var previewWindow = window.open('', 'apcampaign_preview_' + Date.now(), 'popup=yes,width=' + popupWidth + ',height=' + popupHeight + ',left=' + left + ',top=' + top + ',resizable=no,scrollbars=yes');
            if (previewWindow && previewWindow.document) {
                previewWindow.document.open();
                previewWindow.document.write(html);
                previewWindow.document.close();
                if (previewWindow.moveTo) {
                    previewWindow.moveTo(left, top);
                }
                previewWindow.focus();
                return;
            }
            previewPanel.hidden = false;
            qs('iframe', previewPanel).srcdoc = html;
        });

        raw.addEventListener('input', updateRawMeta);

        var dismissTip = qs('[data-apcampaign-dismiss-tip]', form);
        if (dismissTip) {
            dismissTip.addEventListener('click', function () {
                var tip = qs('[data-apcampaign-onboarding]', form);
                if (tip) {
                    tip.remove();
                }
            });
        }

        var clearButton = qs('[data-apcampaign-clear]', form);
        if (clearButton) {
            clearButton.addEventListener('click', function () {
                if (window.confirm('Clear the raw HTML editor?')) {
                    raw.value = '';
                    updateRawMeta();
                }
            });
        }

        var downloadButton = qs('[data-apcampaign-download]', form);
        if (downloadButton) {
            downloadButton.addEventListener('click', function () {
                var blob = new Blob([collectHtml()], {type: 'text/html'});
                var link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'apcampaign-email.html';
                link.click();
                setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000);
            });
        }

        var docxTrigger = qs('[data-apcampaign-docx-trigger]', form);
        var docxInput = qs('[data-apcampaign-docx-file]', form);
        if (docxTrigger && docxInput) {
            docxTrigger.addEventListener('click', function () {
                docxInput.click();
            });
        }
        if (docxInput) {
            docxInput.addEventListener('change', function () {
                var status = qs('[data-apcampaign-docx-status]', form);
                var file = docxInput.files && docxInput.files[0] ? docxInput.files[0] : null;
                if (!file) {
                    return;
                }
                if (!/\.docx$/i.test(file.name || '')) {
                    if (status) {
                        status.innerHTML = '<strong>Choose a Word .docx file.</strong>';
                    }
                    docxInput.value = '';
                    return;
                }
                if (status) {
                    status.textContent = 'Importing ' + file.name + '...';
                }
                var fd = new FormData();
                fd.append('action', 'apcampaign_app_import_docx');
                fd.append('nonce', apCampaignApp.nonce);
                fd.append('docx_file', file);
                post(fd).then(function (data) {
                    raw.value = data.html || '';
                    var nameField = qs('[name="name"]', form);
                    var subjectField = qs('[name="subject_line"]', form);
                    var previewField = qs('[name="preheader_text"]', form);
                    if (data.campaign_name && nameField && !nameField.value) {
                        nameField.value = data.campaign_name;
                    }
                    if (data.subject && subjectField) {
                        subjectField.value = data.subject;
                    }
                    if (data.preview && previewField) {
                        previewField.value = data.preview;
                    }
                    if (status) {
                        var warnings = (data.warnings || []).map(function (item) {
                            return '<li>' + escapeHtml(item) + '</li>';
                        }).join('');
                        status.innerHTML = '<strong>Word import complete.</strong> ' + escapeHtml(String(data.image_count || 0)) + ' image(s), ' + escapeHtml(String(data.cta_count || 0)) + ' CTA button(s).' + (warnings ? '<ul>' + warnings + '</ul>' : '<p>No conversion warnings.</p>');
                    }
                    resultBox.innerHTML = '<p><strong>Word eBlast imported.</strong></p><p>Preview the generated Raw HTML, save as draft, then test before scheduling or sending.</p>';
                    updateRawMeta();
                    dirty = true;
                    updateCommandState();
                }).catch(function (error) {
                    if (status) {
                        status.innerHTML = '<strong>Import failed:</strong> ' + escapeHtml(error.message || error);
                    } else {
                        showError(error);
                    }
                }).finally(function () {
                    docxInput.value = '';
                });
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            saveCampaign().then(function (data) {
                form.setAttribute('data-campaign-id', data.campaign_id);
                resultBox.innerHTML = '<p>Draft saved.</p>';
                dirty = false;
                updateCommandState();
                if (window.history && data.edit_url) {
                    window.history.replaceState({}, '', data.edit_url);
                }
            }).catch(showError);
        });

        function saveCampaign() {
            if (useGrapes) {
                syncGrapes();
            } else {
                sync();
            }
            var fd = new FormData(form);
            fd.append('action', 'apcampaign_app_save_campaign');
            fd.append('nonce', apCampaignApp.nonce);
            fd.append('campaign_id', form.getAttribute('data-campaign-id') || '0');
            return post(fd);
        }

        function renderSettings(settings) {
            resultBox.innerHTML = '<div class="apcampaign-property-editor" data-apcampaign-settings-form><div class="apcampaign-property-head"><strong>Composer Settings</strong><span>Standalone defaults</span></div>' +
                controlInput('Default From Name', 'default_from_name', settings.default_from_name || '', 'Publication name') +
                controlInput('Default From Email', 'default_from_email', settings.default_from_email || '', 'name@example.com', 'email') +
                controlInput('Physical Mailing Address', 'physical_address', settings.physical_address || '', 'Required in every campaign footer') +
                controlRange('Spam Pass Score', 'spam_pass_score', settings.spam_pass_score || 70, 0, 100, '') +
                controlRange('Preflight Link Limit', 'preflight_link_limit', settings.preflight_link_limit || 25, 1, 200, '') +
                controlCheckbox('Restrict retargeting to campaign owner', 'retarget_owner_only', !!settings.retarget_owner_only, 'Default is open for standalone and SaaS use. Enable this when account users should only retarget their own campaigns.') +
                '<div class="apcampaign-property-actions"><button type="button" data-apcampaign-save-settings>Save Settings</button></div><p class="apcampaign-drag-note">Shared provider credentials stay connector-owned. These are composer defaults only.</p></div>';
        }

        function loadTemplates() {
            var fd = new FormData();
            fd.append('action', 'apcampaign_app_templates');
            fd.append('nonce', apCampaignApp.nonce);
            fd.append('composer_mode', modeField.value || 'block');
            post(fd).then(function (data) {
                renderTemplateModal(data);
            }).catch(showError);
        }

        var templatesButton = qs('[data-apcampaign-templates]', form);
        if (templatesButton) {
            templatesButton.addEventListener('click', loadTemplates);
        }

        document.addEventListener('click', function (event) {
            if (event.target.matches('[data-apcampaign-close-modal]') || event.target.classList.contains('apcampaign-modal')) {
                closeTemplateModal();
                return;
            }
            if (event.target.matches('[data-apcampaign-use-template]')) {
                applyTemplate(templatesByKey[event.target.getAttribute('data-apcampaign-use-template') || '']);
                return;
            }
            if (event.target.matches('[data-apcampaign-delete-template]')) {
                var key = event.target.getAttribute('data-apcampaign-delete-template') || '';
                if (!key || !window.confirm('Delete this saved template?')) {
                    return;
                }
                var fd = new FormData();
                fd.append('action', 'apcampaign_app_delete_template');
                fd.append('nonce', apCampaignApp.nonce);
                fd.append('template_key', key);
                post(fd).then(function () {
                    if (resultBox) {
                        resultBox.innerHTML = '<p><strong>Template deleted.</strong></p>';
                    }
                    loadTemplates();
                }).catch(showError);
            }
        });

        var saveTemplateButton = qs('[data-apcampaign-save-template]', form);
        if (saveTemplateButton) {
            saveTemplateButton.addEventListener('click', function () {
                var defaultName = qs('[name="name"]', form).value || 'Campaign Template';
                var templateName = window.prompt('Template name:', defaultName);
                if (!templateName) {
                    return;
                }
                saveCampaign().then(function (data) {
                    form.setAttribute('data-campaign-id', data.campaign_id);
                    var fd = new FormData(form);
                    fd.append('action', 'apcampaign_app_save_template');
                    fd.append('nonce', apCampaignApp.nonce);
                    fd.append('campaign_id', data.campaign_id);
                    fd.append('template_name', templateName);
                    return post(fd);
                }).then(function (data) {
                    resultBox.innerHTML = '<p><strong>Template saved.</strong></p><p>' + escapeHtml(templateName) + '</p>';
                    loadTemplates();
                }).catch(showError);
            });
        }

        var scheduleButton = qs('[data-apcampaign-schedule]', form);
        if (scheduleButton) {
            scheduleButton.addEventListener('click', function () {
                var scheduledAt = window.prompt('Schedule for (YYYY-MM-DD HH:MM in this site\'s timezone):', '');
                if (!scheduledAt) {
                    return;
                }
                saveCampaign().then(function (data) {
                    form.setAttribute('data-campaign-id', data.campaign_id);
                    var fd = new FormData();
                    fd.append('action', 'apcampaign_app_schedule');
                    fd.append('nonce', apCampaignApp.nonce);
                    fd.append('campaign_id', data.campaign_id);
                    fd.append('scheduled_at', scheduledAt);
                    return post(fd);
                }).then(function (data) {
                    resultBox.innerHTML = '<p><strong>Scheduled:</strong> ' + escapeHtml(data.message || data.scheduled_at || 'Campaign scheduled.') + '</p>';
                }).catch(showError);
            });
        }

        var sendButton = qs('[data-apcampaign-send]', form);
        if (sendButton) {
            sendButton.addEventListener('click', function () {
                if (!window.confirm('Send this campaign now to the selected list? Preflight, suppression, and compliance checks will run first.')) {
                    return;
                }
                saveCampaign().then(function (data) {
                    form.setAttribute('data-campaign-id', data.campaign_id);
                    var fd = new FormData();
                    fd.append('action', 'apcampaign_app_send');
                    fd.append('nonce', apCampaignApp.nonce);
                    fd.append('campaign_id', data.campaign_id);
                    return post(fd);
                }).then(function (data) {
                    resultBox.innerHTML = '<p><strong>Send:</strong> ' + escapeHtml(data.message || 'Campaign send started.') + '</p>';
                }).catch(showError);
            });
        }

        document.addEventListener('keydown', function (event) {
            var tag = event.target && event.target.tagName ? event.target.tagName.toLowerCase() : '';
            var editingText = tag === 'input' || tag === 'textarea' || tag === 'select' || event.target.isContentEditable;
            if ((event.metaKey || event.ctrlKey) && !event.shiftKey && event.key.toLowerCase() === 'z') {
                if (!editingText) {
                    event.preventDefault();
                    undo();
                }
            }
            if ((event.metaKey || event.ctrlKey) && (event.key.toLowerCase() === 'y' || (event.shiftKey && event.key.toLowerCase() === 'z'))) {
                if (!editingText) {
                    event.preventDefault();
                    redo();
                }
            }
            if (!useGrapes && !editingText && (event.key === 'Delete' || event.key === 'Backspace') && selectedIndex >= 0 && design.blocks[selectedIndex]) {
                event.preventDefault();
                recordHistory();
                design.blocks.splice(selectedIndex, 1);
                selectedIndex = -1;
                paint();
            }
        });

        qs('[data-apcampaign-preflight]', form).addEventListener('click', function () {
            saveCampaign().then(function (data) {
                form.setAttribute('data-campaign-id', data.campaign_id);
                var fd = new FormData();
                fd.append('action', 'apcampaign_app_run_preflight');
                fd.append('nonce', apCampaignApp.nonce);
                fd.append('campaign_id', data.campaign_id);
                return post(fd);
            }).then(function (data) {
                var failures = (((data.preflight || {}).failures) || []).map(function (item) {
                    return '<li>' + escapeHtml(item.message || item.code) + '</li>';
                }).join('');
                var warnings = (((data.preflight || {}).warnings) || []).map(function (item) {
                    return '<li>' + escapeHtml(item.message || item.code) + '</li>';
                }).join('');
                var suggestions = ((((data.intelligence || {}).analysis || {}).suggestions) || []).slice(0, 6).map(function (item) {
                    return '<li>' + escapeHtml(item) + '</li>';
                }).join('');
                resultBox.innerHTML = '<p><strong>Preflight:</strong> ' + escapeHtml((data.preflight || {}).status || 'unknown') + '</p><p><strong>Score:</strong> ' + escapeHtml((((data.intelligence || {}).analysis || {}).overall_score) || '-') + '</p><ul class="apcampaign-result-list">' + (failures + warnings + suggestions || '<li>No critical findings.</li>') + '</ul>';
            }).catch(showError);
        });

        var spamButton = qs('[data-apcampaign-spam]', form);
        if (spamButton) {
            spamButton.addEventListener('click', function () {
                var fd = new FormData();
                fd.append('action', 'apcampaign_spam_score');
                fd.append('nonce', apCampaignApp.nonce);
                fd.append('subject', qs('[name="subject_line"]', form).value || '');
                fd.append('preheader', qs('[name="preheader_text"]', form).value || '');
                fd.append('html', collectHtml());
                post(fd).then(function (data) {
                    var issues = (data.issues || []).slice(0, 8).map(function (item) {
                        return '<li>' + escapeHtml(item.title || item.code) + ': ' + escapeHtml(item.fix || '') + '</li>';
                    }).join('');
                    resultBox.innerHTML = '<p><strong>Spam score:</strong> ' + escapeHtml(data.score) + ' / 100 (' + escapeHtml(data.grade) + ')</p><ul class="apcampaign-result-list">' + (issues || '<li>No major spam issues found.</li>') + '</ul>';
                }).catch(showError);
            });
        }

        qs('[data-apcampaign-test]', form).addEventListener('click', function () {
            var to = window.prompt('Send test to:', '');
            if (!to) {
                return;
            }
            saveCampaign().then(function (data) {
                form.setAttribute('data-campaign-id', data.campaign_id);
                var fd = new FormData();
                fd.append('action', 'apcampaign_app_test_send');
                fd.append('nonce', apCampaignApp.nonce);
                fd.append('campaign_id', data.campaign_id);
                fd.append('to', to);
                return post(fd);
            }).then(function (data) {
                var accepted = data.sent ? 'accepted' : 'rejected';
                var route = data.transport || 'WordPress mail';
                var confirmation = data.provider_delivery_confirmed ? 'Provider delivery confirmed.' : 'Provider delivery is not confirmed by this test.';
                resultBox.innerHTML = '<p>Test message ' + escapeHtml(accepted) + ' by ' + escapeHtml(route) + '.</p><p>' + escapeHtml(confirmation) + (data.provider_configured ? '' : ' Configure apMailer before expecting a configured delivery route.') + '</p>';
            }).catch(showError);
        });

        function showError(error) {
            resultBox.innerHTML = '<p>Error: ' + escapeHtml(error.message || error) + '</p>';
        }

        switchMode(modeField.value || 'block');
        if (useGrapes) {
            initGrapes();
        } else {
            paint();
        }
        updateRawMeta();
        dirty = false;
        updateCommandState();
        if (new URLSearchParams(window.location.search).get('apcampaign_media') === '1') {
            window.setTimeout(function () {
                openCampaignMediaPicker({title: 'Store media files'});
            }, 150);
        }
    }

    function post(fd) {
        return fetch(apCampaignApp.ajaxUrl, {method: 'POST', body: fd, credentials: 'same-origin'})
            .then(function (response) { return response.json(); })
            .then(function (json) {
                if (!json.success) {
                    throw new Error((json.data && json.data.message) || 'Request failed');
                }
                return json.data;
            });
    }

    function initThemeToggle() {
        var button = qs('[data-apcampaign-theme-toggle]');
        var body = document.body;
        if (!button || !body) {
            return;
        }
        function applyTheme(theme) {
            var light = theme === 'light';
            body.classList.toggle('apcampaign-theme-light', light);
            body.classList.toggle('apcampaign-theme-dark', !light);
            button.classList.toggle('is-light', light);
            button.textContent = '';
            button.title = light ? 'Switch to dark mode' : 'Switch to light mode';
            button.setAttribute('aria-pressed', light ? 'true' : 'false');
            document.dispatchEvent(new CustomEvent('apcampaign:themechange', {detail: {theme: light ? 'light' : 'dark'}}));
        }
        var storageKey = body.classList.contains('apcampaign-context-mmros') ? 'mmros-theme' : 'apcampaign_theme';
        var saved = localStorage.getItem(storageKey) || localStorage.getItem('apcampaign_theme') || 'dark';
        applyTheme(saved === 'light' ? 'light' : 'dark');
        button.addEventListener('click', function () {
            var next = body.classList.contains('apcampaign-theme-light') ? 'dark' : 'light';
            localStorage.setItem(storageKey, next);
            if (storageKey !== 'apcampaign_theme') {
                localStorage.setItem('apcampaign_theme', next);
            }
            applyTheme(next);
        });
    }

    function initRetargetActions() {
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-apcampaign-retarget]');
            if (!button) {
                return;
            }
            var campaignId = button.getAttribute('data-apcampaign-retarget') || '0';
            button.disabled = true;
            button.textContent = 'Loading...';
            var previewFd = new FormData();
            previewFd.append('action', 'apcampaign_app_retarget_preview');
            previewFd.append('nonce', apCampaignApp.nonce);
            previewFd.append('campaign_id', campaignId);
            post(previewFd).then(function (data) {
                button.disabled = false;
                button.textContent = 'Retarget';
                showRetargetModal(data);
            }).catch(function (error) {
                button.disabled = false;
                button.textContent = 'Retarget';
                window.alert(error.message || 'Retargeting details could not be loaded.');
            });
        });
    }

    function closeRetargetModal() {
        var modal = qs('[data-apcampaign-retarget-modal]');
        if (modal && modal.parentNode) {
            modal.parentNode.removeChild(modal);
        }
    }

    function showRetargetModal(data) {
        closeRetargetModal();
        var campaign = data.campaign || {};
        var counts = data.counts || {};
        var modal = document.createElement('div');
        modal.className = 'apcampaign-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('data-apcampaign-retarget-modal', '1');
        modal.innerHTML = '<div class="apcampaign-modal-card apcampaign-retarget-card"><header><div><p>Retarget Campaign</p><h2>' + escapeHtml(campaign.name || 'Campaign') + '</h2></div><button type="button" data-apcampaign-close-retarget>×</button></header><div class="apcampaign-retarget-details"><p><strong>Original subject:</strong> ' + escapeHtml(campaign.subject_line || 'No subject') + '</p><p><strong>Status:</strong> ' + escapeHtml(campaign.status || '') + ' · <strong>Updated:</strong> ' + escapeHtml(campaign.updated_at || '') + '</p></div><label class="apcampaign-retarget-subject"><span>Optional new subject line</span><input type="text" data-apcampaign-retarget-subject value="' + escapeHtml(campaign.subject_line || '') + '"></label><div class="apcampaign-retarget-grid"><button type="button" data-apcampaign-retarget-choice="not_opened"><span>Did Not Open</span><strong>' + escapeHtml(counts.not_opened || 0) + '</strong></button><button type="button" data-apcampaign-retarget-choice="opened"><span>Opened</span><strong>' + escapeHtml(counts.opened || 0) + '</strong></button><button type="button" data-apcampaign-retarget-choice="clicked"><span>Clicked</span><strong>' + escapeHtml(counts.clicked || 0) + '</strong></button><button type="button" data-apcampaign-retarget-choice="all"><span>All Prior Recipients</span><strong>' + escapeHtml(counts.all || 0) + '</strong></button></div></div>';
        document.body.appendChild(modal);
        qsa('[data-apcampaign-retarget-choice]', modal).forEach(function (choice) {
            choice.addEventListener('click', function () {
                var behavior = choice.getAttribute('data-apcampaign-retarget-choice') || 'not_opened';
                var subject = (qs('[data-apcampaign-retarget-subject]', modal) || {}).value || '';
                choice.disabled = true;
                choice.querySelector('span').textContent = 'Creating draft...';
                var fd = new FormData();
                fd.append('action', 'apcampaign_app_retarget');
                fd.append('nonce', apCampaignApp.nonce);
                fd.append('campaign_id', String(campaign.id || '0'));
                fd.append('behavior', behavior);
                fd.append('subject_line', subject);
                post(fd).then(function (created) {
                    if (created.edit_url) {
                        window.location.href = created.edit_url;
                    }
                }).catch(function (error) {
                    choice.disabled = false;
                    choice.querySelector('span').textContent = choice.getAttribute('data-original-label') || 'Retarget';
                    window.alert(error.message || 'Retargeting draft could not be created.');
                });
            });
            choice.setAttribute('data-original-label', (choice.querySelector('span') || {}).textContent || 'Retarget');
        });
    }

    function initArchiveActions() {
        document.addEventListener('click', function (event) {
            var closeButton = event.target.closest('[data-apcampaign-close-retarget]');
            if (closeButton || event.target.matches('[data-apcampaign-retarget-modal]')) {
                closeRetargetModal();
                return;
            }
            var button = event.target.closest('[data-apcampaign-archive]');
            if (!button) {
                return;
            }
            if (!window.confirm('Archive this campaign? It will move out of Recent Campaigns.')) {
                return;
            }
            var campaignId = button.getAttribute('data-apcampaign-archive') || '0';
            var row = button.closest('.apcampaign-campaign-row');
            button.disabled = true;
            button.textContent = 'Archiving...';
            var fd = new FormData();
            fd.append('action', 'apcampaign_app_archive');
            fd.append('nonce', apCampaignApp.nonce);
            fd.append('campaign_id', campaignId);
            post(fd).then(function () {
                if (row && row.parentNode) {
                    row.parentNode.removeChild(row);
                }
            }).catch(function (error) {
                button.disabled = false;
                button.textContent = 'Archive';
                window.alert(error.message || 'Campaign could not be archived.');
            });
        });
    }

    function initDeliveryReview() {
        document.addEventListener('click', function (event) {
            var cancel = event.target.closest('[data-apcampaign-cancel-preparation]');
            if (cancel) {
                if (!window.confirm('Cancel this incomplete preparation? No messages will be sent by it.')) return;
                cancel.disabled = true;
                var cancelData = new FormData();
                cancelData.append('action', 'apcampaign_cancel_preparation');
                cancelData.append('nonce', apCampaignApp.nonce);
                cancelData.append('job_id', cancel.getAttribute('data-apcampaign-cancel-preparation'));
                post(cancelData).then(function () { window.location.reload(); }).catch(function (error) {
                    cancel.disabled = false;
                    window.alert(error.message || 'The preparation could not be cancelled.');
                });
                return;
            }
            var resume = event.target.closest('[data-apcampaign-resume-job]');
            if (resume) {
                if (!window.confirm('Resume the remaining queued messages?')) return;
                resume.disabled = true;
                var resumeData = new FormData();
                resumeData.append('action', 'apcampaign_resume_reviewed');
                resumeData.append('nonce', apCampaignApp.nonce);
                resumeData.append('job_id', resume.getAttribute('data-apcampaign-resume-job'));
                post(resumeData).then(function () { window.location.reload(); }).catch(function (error) {
                    resume.disabled = false;
                    window.alert(error.message || 'The send could not be resumed.');
                });
                return;
            }
            var button = event.target.closest('[data-apcampaign-review-job]');
            if (!button) return;
            var outcome = button.getAttribute('data-apcampaign-review-outcome');
            if (!window.confirm('Confirm this result from your provider logs? The uncertain message will not be resent and the remaining queue will continue.')) return;
            button.disabled = true;
            var fd = new FormData();
            fd.append('action', 'apcampaign_review_delivery');
            fd.append('nonce', apCampaignApp.nonce);
            fd.append('job_id', button.getAttribute('data-apcampaign-review-job'));
            fd.append('recipient_id', button.getAttribute('data-apcampaign-review-recipient'));
            fd.append('outcome', outcome);
            post(fd).then(function () { window.location.reload(); }).catch(function (error) {
                button.disabled = false;
                window.alert(error.message || 'Review could not be saved.');
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initDeliveryReview();
        initThemeToggle();
        initRetargetActions();
        initArchiveActions();
        qsa('[data-apcampaign-composer]').forEach(initComposer);
    });
}());
