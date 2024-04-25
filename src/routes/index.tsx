import {component$, Resource, useResource$, useStore, $, useSignal} from '@builder.io/qwik';
import type {DocumentHead} from "@builder.io/qwik-city";
import Plugin from '~/interfaces/plugin';
import { formatDate, truncateString } from '~/utils/utils';
import PluginHistoryData from "~/interfaces/pluginHistoryData";

interface SortState {
    field: keyof Plugin | null;
    direction: 'asc' | 'desc';
}

export default component$(() => {
    const sortState = useStore<SortState>({ field: null, direction: 'asc' });
    const isInitialLoad = useSignal(true);

    const pluginsResource = useResource$<Plugin[]>(async ({ track, cleanup }) => {
        const response = await fetch('http://api.runelite.phyce.dev/plugins');
        // const response = await fetch('http://osrs-stats:8080/plugins');
        const jsonResponse = await response.json();
        if (jsonResponse.success) return jsonResponse.data;
        else throw new Error('Failed to fetch plugins');
    });

    const pluginSignal = useSignal<Plugin[] | null>(null);

    return (
      <div>
            <Resource
                value={pluginsResource}
                onResolved={(plugins) => {

                    if (isInitialLoad.value) {
                        pluginSignal.value = plugins;
                        isInitialLoad.value = false; // Set flag to false after initial load
                    }

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
                        pluginSignal.value = sortPlugins(plugins, field, newDirection);
                    });

                    return(
                    <div class="overflow-x-auto relative shadow-md sm:rounded-lg p-2">
                        <table class="w-full text-sm text-left text-orange-100 text-gray-400 rounded auto">
                            <thead
                                class="text-xs uppercase bg-orange-800 text-orange-100 rounded">
                            <tr>
                                <th scope="col" class="py-3 px-3 cursor-pointer" onClick$={() => handleSort('name')}>
                                    Name {sortState.field === 'name' ? (sortState.direction === 'asc' ? '↑' : '↓') : ''}
                                </th>
                                <th scope="col" class="py-3 px-3 cursor-pointer" onClick$={() => handleSort('current_installs')}>
                                    Current Installs {sortState.field === 'current_installs' ? (sortState.direction === 'asc' ? '↑' : '↓') : ''}
                                </th>
                                <th scope="col" class="py-3 px-3 cursor-pointer" onClick$={() => handleSort('all_time_high')}>
                                    All Time High {sortState.field === 'all_time_high' ? (sortState.direction === 'asc' ? '↑' : '↓') : ''}
                                </th>
                                <th scope="col" class="py-3 px-3 cursor-pointer" onClick$={() => handleSort('description')}>
                                    Description {sortState.field === 'description' ? (sortState.direction === 'asc' ? '↑' : '↓') : ''}
                                </th>
                                <th scope="col" class="py-3 px-3 cursor-pointer" onClick$={() => handleSort('updated_on')}>
                                    Updated On {sortState.field === 'updated_on' ? (sortState.direction === 'asc' ? '↑' : '↓') : ''}
                                </th>
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
                                                class="text-organge-600 hover:underline text-orange-500"
                                                href={"https://runelite.net/plugin-hub/show/" + plugin.name}
                                                target="_blank">{plugin.display}</a>
                                        </td>
                                        <td class="py-1 px-3">{plugin.current_installs}</td>
                                        <td class="py-1 px-3">{plugin.all_time_high}</td>

                                        <td class="py-1 px-3">{truncateString(plugin.description, 100, '...')}</td>
                                        <td class="py-1 px-3">{formatDate(plugin.updated_on)}</td>
                                        <td class="py-1 px-3">
                                            <a href={"/" + plugin.name} target="_blank" class="text-organge-600 hover:underline text-orange-500">View Stats</a>
                                        </td>
                                    </tr>
                                ))
                            }
                            </tbody>
                        </table>
                    </div>
                )}}
            />
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
