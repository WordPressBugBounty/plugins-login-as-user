/**
 * Adds the "Login as User" column to the SureCart DataViews lists (orders & customers).
 * SureCart exposes no hook for that React table, so we decorate its rendered DOM.
 */
(function () {
    'use strict';

    var cfg = window.w357LoginAsUserSureCart;
    if (!cfg) {
        return;
    }

    var cache = {};
    var pending = {};
    var rowIdPattern = /[?&]id=([\w-]+)/;

    // The cell holding the record's edit link ("Order" / "Name"); our column goes right after it so it
    // stays visible without horizontal scrolling (the table's sticky Actions column covers the far right).
    function primaryCell(row) {
        var link = row.querySelector('a.sc-list-link[href*="action=edit"]');
        return link ? link.closest('td') : null;
    }

    function rowId(row) {
        var link = row.querySelector('a.sc-list-link[href*="action=edit"]');
        var match = link && rowIdPattern.exec(link.getAttribute('href'));
        return match ? match[1] : null;
    }

    function cellFor(row, primary) {
        var cell = row.querySelector('td.column-loginasuser_col');
        if (!cell) {
            cell = document.createElement('td');
            cell.className = 'column-loginasuser_col';
            primary.after(cell);
        }
        return cell;
    }

    function fetchButtons(ids) {
        var body = new URLSearchParams({
            action: 'w357_login_as_user_surecart_buttons',
            nonce: cfg.nonce,
            type: cfg.type
        });
        ids.forEach(function (id) {
            body.append('ids[]', id);
            pending[id] = true;
        });

        return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (response) { return response.json(); })
            .then(function (json) {
                if (json && json.success) {
                    Object.assign(cache, json.data);
                }
            })
            .catch(function () { /* leave the cells empty; the next DOM change retries */ })
            .then(function () {
                ids.forEach(function (id) { delete pending[id]; });
                decorate();
            });
    }

    function decorate() {
        var table = document.querySelector('table.dataviews-view-table');
        if (!table) {
            return;
        }

        var headRow = table.querySelector('thead tr');
        var firstRow = table.querySelector('tbody tr');
        var firstPrimary = firstRow && primaryCell(firstRow);
        if (headRow && firstPrimary && !headRow.querySelector('th.column-loginasuser_col')) {
            var th = document.createElement('th');
            th.className = 'column-loginasuser_col';
            th.textContent = cfg.columnTitle;
            headRow.children[Array.prototype.indexOf.call(firstRow.children, firstPrimary)].after(th);
        }

        var missing = [];
        table.querySelectorAll('tbody tr').forEach(function (row) {
            var id = rowId(row);
            var primary = primaryCell(row);
            if (!id || !primary) {
                return;
            }
            var cell = cellFor(row, primary);
            if (cache[id] !== undefined) {
                if (cell.getAttribute('data-id') !== id) {
                    cell.innerHTML = cache[id];
                    cell.setAttribute('data-id', id);
                }
            } else if (!pending[id]) {
                missing.push(id);
            }
        });

        if (missing.length) {
            fetchButtons(missing);
        }
    }

    // Our own DOM writes re-trigger the observer; decorate() is idempotent, so it settles after one extra pass.
    var scheduled = false;
    new MutationObserver(function () {
        if (!scheduled) {
            scheduled = true;
            setTimeout(function () {
                scheduled = false;
                decorate();
            }, 50);
        }
    }).observe(document.body, { childList: true, subtree: true });

    decorate();
})();
