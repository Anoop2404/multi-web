{{--
    Shared client-side auto-fit pass for the recipient-name and body overlay fields in
    certificate-body.blade.php's has-background branch. Included by both
    certificate-print.blade.php (one certificate) and certificate-print-all.blade.php
    (many certificates concatenated, hence the querySelectorAll over every .page rather
    than a single lookup). Runs identically whether a human opens this in a normal
    browser tab or a headless Chromium render service loads it for batch PDF capture —
    one implementation, not two.
--}}
<style>
    .overlay-field.recipient.cert-clamped {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>
<script>
(function () {
    function fitTextToBox(el, floorPx, allowedBottom, truncate) {
        if (!el || allowedBottom == null) {
            return;
        }

        var margin = 8;

        function overflows() {
            return (el.offsetTop + el.scrollHeight) > (allowedBottom - margin);
        }

        function shrinkToFit() {
            var size = originalSize;
            el.style.fontSize = size + 'px';
            while (size > floorPx && overflows()) {
                size -= 0.5;
                el.style.fontSize = size + 'px';
            }
        }

        var originalSize = parseFloat(getComputedStyle(el).fontSize) || floorPx;

        if (!overflows()) {
            return;
        }

        shrinkToFit();

        if (overflows() && truncate && truncate(el)) {
            shrinkToFit();
        }
    }

    // The full "A, B, C and D" sentence has no natural cap, so once it's still too tall
    // at the font floor, shorten the underlying list itself rather than let it run off
    // the certificate — targets the addressable span certificate-body.blade.php only
    // renders once there are more than 3 items (see resolveFieldValues()/item_titles).
    function truncateItemList(el) {
        var span = el.querySelector('.cert-item-list');
        if (!span) {
            return false;
        }

        var raw = span.getAttribute('data-items-json');
        var items;
        try {
            items = raw ? JSON.parse(raw) : null;
        } catch (e) {
            return false;
        }
        if (!Array.isArray(items) || items.length <= 3) {
            return false;
        }

        var shortText = items.slice(0, 3).join(', ') + ' and ' + (items.length - 3) + ' more';
        var strongChild = span.querySelector('strong');
        if (strongChild) {
            strongChild.textContent = shortText;
        } else {
            span.textContent = shortText;
        }

        return true;
    }

    // A name still overflowing at the font floor is a defensive last resort (never seen
    // in practice against real names at this box width) — clamp to 2 lines with an
    // ellipsis rather than cut a person's name down to a handful of characters.
    function clampRecipient(el) {
        if (el.classList.contains('cert-clamped')) {
            return false;
        }
        el.classList.add('cert-clamped');
        return true;
    }

    // certificate-body.blade.php's overlayFieldStyle() gives the body field an explicit
    // CSS `bottom` when the template configures one (the artwork's own fillable-zone
    // edge, e.g. just above a "Congratulations" graphic) — position:absolute with both
    // top and bottom set gives the box a fixed height, so its own offsetTop+offsetHeight
    // *is* that edge regardless of how tall the content inside has grown. That's a much
    // more reliable boundary than the next-field heuristic below, which was guessing from
    // wherever the date/uuid fields happen to be (often far below the artwork's actual
    // usable area, e.g. a uuid pinned near the page's bottom edge) and let long content
    // visually run into background art the script has no other way to know about.
    function computeAllowedBottom(page, body, dateEl, uuidEl) {
        if (getComputedStyle(body).bottom !== 'auto') {
            return body.offsetTop + body.offsetHeight;
        }
        var nextTops = [dateEl, uuidEl].filter(Boolean).map(function (e) {
            return e.offsetTop;
        });
        return nextTops.length ? Math.min.apply(null, nextTops) : (page.offsetHeight - 16);
    }

    function fitPage(page) {
        var recipient = page.querySelector('.overlay-field.recipient');
        var body = page.querySelector('.overlay-field.body');
        var dateEl = page.querySelector('.overlay-field.cert-date');
        var uuidEl = page.querySelector('.overlay-field.uuid');

        if (recipient && body) {
            fitTextToBox(recipient, 6, body.offsetTop, clampRecipient);
        }

        if (body) {
            fitTextToBox(body, 6, computeAllowedBottom(page, body, dateEl, uuidEl), truncateItemList);
            centerWithinZone(body);
        }
    }

    // Run once shrink/truncate has settled on a final size. A short achievement sentence
    // (few or no participated items) otherwise sits flush against the top of a tall
    // reserved zone, leaving a visibly empty gap above the artwork's "Congratulations"
    // graphic — pull it down into the leftover space instead. Weighted toward the bottom
    // (rather than a plain 50/50 center) because a gap directly above the Congratulations
    // graphic reads as "unfinished," while the same space just below the photo reads as
    // normal breathing room. Skipped entirely when content still fills/overflows the zone,
    // so this never fights the shrink pass.
    function centerWithinZone(el) {
        if (getComputedStyle(el).bottom === 'auto') {
            return;
        }
        var slack = el.offsetHeight - el.scrollHeight;
        if (slack > 0) {
            el.style.top = (el.offsetTop + slack * 0) + 'px';
        }
    }

    function run() {
        var pages = document.querySelectorAll('.page.has-background');
        for (var i = 0; i < pages.length; i++) {
            fitPage(pages[i]);
        }
        window.__certFitDone = true;
    }

    function whenReady() {
        var fontsReady = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
        fontsReady.then(run).catch(run);
    }

    if (document.readyState === 'complete') {
        whenReady();
    } else {
        window.addEventListener('load', whenReady);
    }
})();
</script>
