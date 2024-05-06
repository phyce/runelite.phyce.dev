import {
    component$,
    useStore,
    $,
    useSignal,
    useContext,
    useVisibleTask$
} from '@builder.io/qwik';
import type {DocumentHead, RequestHandler} from "@builder.io/qwik-city";
import type Plugin from '~/interfaces/plugin';
import { formatDate, truncateString, updateUrlParamsFromCookie } from '~/utils/utils';
import {globalContextId} from "~/providers/global";

interface SortState {
    field: keyof Plugin | null;
    direction: 'asc' | 'desc';
}

interface Column {
    name: string;
    display: string;
}

export default component$(() => {
    const sortState = useStore<SortState>({ field: 'name', direction: 'asc' });
    const globalContext = useContext(globalContextId);
    const pluginSignal = useSignal<Plugin[]>(globalContext.plugins.value);

    const columns: Column[] = [
        {name: "name", display: "Name"},
        {name: "current_installs", display: "Current Installs"},
        {name: "all_time_high", display: "All Time High"},
        {name: "description", display: "Description"},
        {name: "updated_on", display: "Updated On"},
    ]

    const handleSort = $((field: keyof Plugin) => {
        const newDirection = sortState.field === field && sortState.direction === 'asc' ? 'desc' : 'asc';
        sortState.field = field;
        sortState.direction = newDirection;

        const sortPlugins = (plugins: Plugin[], field: keyof Plugin, direction: 'asc' | 'desc') => {
            return [...plugins].sort((a, b) => {
                const valueA = a[field];
                const valueB = b[field];
                if (typeof valueA === 'number' && typeof valueB === 'number') {
                    return direction === 'asc' ? valueA - valueB : valueB - valueA;
                }
                return direction === 'asc' ? `${valueA}`.localeCompare(`${valueB}`) : `${valueB}`.localeCompare(`${valueA}`);
            });
        };

        pluginSignal.value = sortPlugins(pluginSignal.value, field, newDirection);
    });

    useVisibleTask$(() => {
        updateUrlParamsFromCookie();
    });

    return (
      <div>
        <div class="overflow-x-auto relative shadow-md sm:rounded-lg p-2">
            <table class="w-full text-sm text-left text-orange-100 text-gray-400 rounded auto">
                <thead
                    class="text-xs uppercase bg-orange-800 text-orange-100 rounded">
                <tr>
                    {
                        columns.map((column) => (
                            <th scope="col" class="py-3 px-3 cursor-pointer hover:underline" onClick$={() => handleSort(column.name as keyof Plugin)}>
                                {column.display} {sortState.field === column.name ? (sortState.direction === 'asc' ? '↑' : '↓') : ''}
                            </th>
                        ))
                    }
                    <th scope="col" class="py-3 px-3"></th>
                </tr>
                </thead>
                <tbody>
                {
                    pluginSignal.value?.map((plugin) => (
                        <tr class="border-b bg-neutral-800 border-gray-700 text-gray-300 hover:bg-neutral-700"
                            title={plugin.warning}
                            key={plugin.id}>
                            <td class="py-1 px-3">
                                <a
                                    class="hover:underline text-orange-500"
                                    href={"https://runelite.net/plugin-hub/show/" + plugin.name}
                                    target="_blank">{plugin.display ?? plugin.name}</a>
                            </td>
                            <td class="py-1 px-3">{plugin.current_installs}</td>
                            <td class="py-1 px-3">{plugin.all_time_high}</td>

                            <td class="py-1 px-3">{truncateString(plugin.description, 100, '...')}</td>
                            <td class="py-1 px-3">{formatDate(plugin.updated_on)}</td>
                            <td class="py-1 px-3">
                                <a href={"/" + plugin.name} class="hover:underline text-orange-500">View Stats</a>
                            </td>
                        </tr>
                    ))
                }
                </tbody>
            </table>
        </div>
      </div>
    );
});

export const head: DocumentHead = {
    title: "Runelite plugin stats - phyce.dev",
    meta: [
        {
            name: "A site to view active install history for runelite plugins",
            content: "made by phyce.dev",
        },
    ],
};