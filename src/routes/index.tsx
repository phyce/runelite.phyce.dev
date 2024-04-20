import { component$, Resource, useResource$ } from '@builder.io/qwik';
import type {DocumentHead} from "@builder.io/qwik-city";

interface Plugin {
  id: number;
  name: string;
  git_repo: string;
  display: string;
  support: string;
  description: string;
  warning: string;
  updated_on: string;
  all_time_high: number;
  current_installs: number;
}

function formatDate(dateString: string, userLocale: string = 'en-GB'): string {
    const options: Intl.DateTimeFormatOptions = {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    };
    return new Intl.DateTimeFormat(userLocale, options).format(new Date(dateString));
}

function truncateString(str: string, num: number, suffix: string = '…'): string {
    if (str.length <= num) {
        return str;
    }
    return str.slice(0, num) + suffix;
}

export default component$(() => {
  const pluginsResource = useResource$<Plugin[]>(async ({ track, cleanup }) => {
    const response = await fetch('http://localhost:8080/plugins'); // Adjust the URL/port as necessary
    return response.json();
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
                                                href={"/" + plugin.name}
                                                target="_blank">{plugin.current_installs}</a>
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
