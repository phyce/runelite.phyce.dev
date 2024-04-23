import { component$, Resource, useResource$ } from '@builder.io/qwik';
import type {DocumentHead} from "@builder.io/qwik-city";
import Plugin from '~/interfaces/plugin';
import { formatDate, truncateString } from '~/utils/utils';

export default component$(() => {
    const pluginsResource = useResource$<Plugin[]>(async ({ track, cleanup }) => {
        // const response = await fetch('http://127.0.0.1:8080/plugins');
        const response = await fetch('http://osrs-stats:8080/plugins');
        const jsonResponse = await response.json();
        if (jsonResponse.success) return jsonResponse.data;
        else throw new Error('Failed to fetch plugins');
    });

    return (
      <div>
            <Resource
                value={pluginsResource}
                onResolved={(plugins) => (
                    <div class="overflow-x-auto relative shadow-md sm:rounded-lg p-2">
                        <table class="w-full text-sm text-left text-orange-100 text-gray-400 rounded auto">
                            <thead
                                class="text-xs uppercase bg-orange-800 text-orange-100 rounded">
                            <tr>
                                <th scope="col" class="py-3 px-3">Name</th>
                                <th scope="col" class="py-3 px-3">Current Installs</th>
                                <th scope="col" class="py-3 px-3">All Time High</th>
                                <th scope="col" class="py-3 px-3">Description</th>
                                <th scope="col" class="py-3 px-3">Updated On</th>
                                <th scope="col" class="py-3 px-3"></th>
                            </tr>
                            </thead>
                            <tbody>
                            <Resource
                                value={pluginsResource}
                                onResolved={(plugins) => (
                                    plugins.map((plugin) => (
                                        <tr class="border-b bg-neutral-800 border-gray-700 text-gray-300"
                                            title={plugin.warning}
                                            key={plugin.id}>
                                            <td class="py-1 px-3">
                                                <a
                                                    class="text-organge-600 hover:underline text-orange-500"
                                                    href={"https://runelite.net/plugin-hub/show/" + plugin.name}
                                                    target="_blank">{plugin.display}</a>
                                            </td>
                                            <td class="py-1 px-3">
                                                <a
                                                    class="text-organge-600 hover:underline text-orange-500"
                                                    href={"/" + plugin.name}>
                                                    {plugin.current_installs}
                                                </a>
                                            </td>
                                            <td class="py-1 px-3">{plugin.all_time_high}</td>

                                            <td class="py-1 px-3">{truncateString(plugin.description, 100, '...')}</td>
                                            <td class="py-1 px-3">{formatDate(plugin.updated_on)}</td>
                                            <td class="py-1 px-3">
                                                <a href={plugin.git_repo} target="_blank" class="text-organge-600 hover:underline text-orange-500">View Repo</a>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            />
                            </tbody>
                        </table>
                    </div>
                )}
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
