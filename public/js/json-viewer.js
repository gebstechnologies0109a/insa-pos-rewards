/**
 * INSA POS — JSON Viewer
 * Syntax highlighting, expand/collapse, and copy-to-clipboard for JSON data.
 */

function jsonViewerSyntaxHighlight(obj) {
    var json = JSON.stringify(obj, null, 2);
    if (!json) return '';
    return json
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"(\\u[a-fA-F0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?/g, function (match) {
            var cls = 'jv-string';
            if (/:\s*$/.test(match)) {
                cls = 'jv-key';
            }
            return '<span class="' + cls + '">' + match + '</span>';
        })
        .replace(/\b(true|false)\b/g, '<span class="jv-bool">$1</span>')
        .replace(/\b(null)\b/g, '<span class="jv-null">$1</span>')
        .replace(/\b(-?\d+\.?\d*)\b/g, '<span class="jv-number">$1</span>');
}

function jsonViewerRender(elementId, data) {
    var el = document.getElementById(elementId);
    if (el) {
        el.innerHTML = jsonViewerSyntaxHighlight(data);
    }
}

function jsonViewerCopy(viewerId) {
    var raw = document.getElementById(viewerId + '-code');
    if (!raw) return;
    var text = raw.textContent || '';
    navigator.clipboard.writeText(text).then(function () {
        var btn = document.querySelector('#' + viewerId + ' [data-copy-btn]');
        if (btn) {
            var orig = btn.textContent;
            btn.textContent = 'Copied!';
            setTimeout(function () { btn.textContent = orig; }, 1500);
        }
    });
}

function jsonViewerToggle(viewerId) {
    var code = document.getElementById(viewerId + '-code');
    if (!code) return;
    var pre = code.closest('pre');
    if (!pre) return;
    if (pre.style.display === 'none') {
        pre.style.display = '';
    } else {
        pre.style.display = 'none';
    }
    var btn = document.querySelector('#' + viewerId + ' [data-toggle-btn]');
    if (btn) {
        btn.textContent = pre.style.display === 'none' ? 'Expand' : 'Collapse';
    }
}
