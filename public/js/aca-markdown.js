/**
 * AcaMarkdown — renderer Markdown ringan & aman untuk jawaban Aca.
 * Semua teks di-escape terlebih dahulu; hanya tag yang dihasilkan di sini
 * yang ditampilkan. Mendukung bold, italic, inline code, bullet/numbered list,
 * paragraf, heading sederhana, dan tabel pipe.
 */
(function (global) {
    'use strict';

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function inline(text) {
        return text
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>')
            .replace(/`([^`]+)`/g, '<code>$1</code>');
    }

    function isTableRow(line) {
        return /^\s*\|.*\|\s*$/.test(line);
    }

    function isTableSeparator(line) {
        return /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)+\|?\s*$/.test(line);
    }

    function splitCells(line) {
        var trimmed = line.trim();
        if (trimmed.charAt(0) === '|') trimmed = trimmed.slice(1);
        if (trimmed.charAt(trimmed.length - 1) === '|') trimmed = trimmed.slice(0, -1);
        return trimmed.split('|').map(function (cell) { return cell.trim(); });
    }

    function renderTable(headerLine, bodyLines) {
        var headers = splitCells(headerLine);
        var html = '<div class="table-responsive my-2"><table class="table table-sm table-bordered align-middle mb-0 aca-md-table"><thead><tr>';
        headers.forEach(function (h) {
            html += '<th>' + inline(h) + '</th>';
        });
        html += '</tr></thead><tbody>';
        bodyLines.forEach(function (line) {
            var cells = splitCells(line);
            html += '<tr>';
            for (var i = 0; i < headers.length; i++) {
                html += '<td>' + inline(cells[i] || '') + '</td>';
            }
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        return html;
    }

    function render(text) {
        if (text === null || typeof text === 'undefined' || text === '') {
            return '';
        }

        var lines = escapeHtml(text).replace(/\r\n?/g, '\n').split('\n');
        var out = [];
        var paragraph = [];
        var listType = null;
        var listItems = [];
        var i = 0;

        function flushParagraph() {
            if (paragraph.length) {
                out.push('<p class="mb-2">' + inline(paragraph.join('<br>')) + '</p>');
                paragraph = [];
            }
        }

        function flushList() {
            if (listType && listItems.length) {
                out.push('<' + listType + ' class="mb-2 ps-3">' + listItems.join('') + '</' + listType + '>');
            }
            listType = null;
            listItems = [];
        }

        function flushAll() {
            flushParagraph();
            flushList();
        }

        while (i < lines.length) {
            var line = lines[i];
            var trimmed = line.trim();

            if (trimmed === '') {
                flushAll();
                i++;
                continue;
            }

            // Tabel pipe
            if (isTableRow(line) && i + 1 < lines.length && isTableSeparator(lines[i + 1])) {
                flushAll();
                var headerLine = line;
                var body = [];
                i += 2;
                while (i < lines.length && isTableRow(lines[i]) && lines[i].trim() !== '') {
                    body.push(lines[i]);
                    i++;
                }
                out.push(renderTable(headerLine, body));
                continue;
            }

            // Heading sederhana
            var heading = trimmed.match(/^(#{1,4})\s+(.+)$/);
            if (heading) {
                flushAll();
                var level = Math.min(4, heading[1].length + 2);
                out.push('<h' + level + ' class="fw-bold mt-2 mb-1" style="font-size:' + (level === 3 ? '1.02rem' : (level === 4 ? '0.95rem' : '1.1rem')) + ';">' + inline(heading[2]) + '</h' + level + '>');
                i++;
                continue;
            }

            if (/^(-{3,}|\*{3,})$/.test(trimmed)) {
                flushAll();
                out.push('<hr class="my-2">');
                i++;
                continue;
            }

            // Bullet list
            var bullet = line.match(/^\s*[-*•]\s+(.+)$/);
            if (bullet) {
                flushParagraph();
                if (listType !== 'ul') {
                    flushList();
                    listType = 'ul';
                }
                listItems.push('<li>' + inline(bullet[1]) + '</li>');
                i++;
                continue;
            }

            // Numbered list
            var numbered = line.match(/^\s*\d+[.)]\s+(.+)$/);
            if (numbered) {
                flushParagraph();
                if (listType !== 'ol') {
                    flushList();
                    listType = 'ol';
                }
                listItems.push('<li>' + inline(numbered[1]) + '</li>');
                i++;
                continue;
            }

            // Paragraf biasa
            flushList();
            paragraph.push(trimmed);
            i++;
        }

        flushAll();
        return out.join('');
    }

    global.AcaMarkdown = { render: render, escapeHtml: escapeHtml };
})(typeof window !== 'undefined' ? window : this);
