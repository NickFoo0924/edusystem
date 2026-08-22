{{--
    The "View all" control that sits at the right of a section header and opens
    the capped list underneath it.

    It appears only when there is actually something hidden -- a list of two
    rows gets no control at all -- and it toggles, so a reader who opens
    eighteen rows can put them away again without reloading.

    No count in the label: the heading already says how many there are, and
    repeating it in the button adds nothing.
--}}

@props(['for', 'total' => 0, 'showing' => 3])

@if ($total > $showing)
    <button type="button"
            data-toggle-list="{{ $for }}"
            aria-controls="{{ $for }}"
            aria-expanded="false"
            class="text-sm font-medium text-blue-700 transition hover:text-blue-900">
        View all
    </button>
@endif

@once
    <script>
        // One listener for every capped list on the page.
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-toggle-list]');

            if (! button) {
                return;
            }

            var list = document.getElementById(button.dataset.toggleList);

            if (! list) {
                return;
            }

            var open = list.classList.toggle('is-open');

            button.textContent = open ? 'Show less' : 'View all';
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    </script>
@endonce
