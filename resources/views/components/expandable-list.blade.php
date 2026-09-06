{{--
    LearnSync -- Blade view
    Shared: project-wide infrastructure
    @author Serena Lim Sze Kee, Foo Chong Xian, Ong Shun Yan, Wong Siew Lam, Ong Kwong Wei
--}}
{{--
    A list that shows its first three rows until the reader asks for the rest.

    A dashboard is meant to be read at a glance, and an eighteen-row queue
    pushes everything below it off the screen. The whole list is still
    rendered -- nothing is fetched again when it opens, and find-on-page still
    reaches the hidden rows -- only the display is capped.

    The cap is plain CSS on .expandable-list in layout.blade.php, so the rows
    are hidden on first paint rather than appearing and then vanishing once a
    script runs.

    Pair it with <x-list-toggle> in the section header, matching on id:

        <div class="flex items-center justify-between ...">
            <h2>Awaiting your review</h2>
            <x-list-toggle for="review-queue" :total="$items->count()" />
        </div>

        <x-expandable-list id="review-queue">
            @foreach ($items as $item) <li>...</li> @endforeach
        </x-expandable-list>
--}}

@props(['id'])

<ul id="{{ $id }}" {{ $attributes->merge(['class' => 'divide-y divide-gray-100 expandable-list']) }}>
    {{ $slot }}
</ul>
