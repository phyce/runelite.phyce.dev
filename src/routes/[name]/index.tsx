import { component$, Resource, useResource$ } from '@builder.io/qwik';
import type {DocumentHead} from "@builder.io/qwik-city";
import Plugin from '~/interfaces/plugin';
import { formatDate, formatNumber } from '~/utils/utils';
import { useLocation } from '@builder.io/qwik-city';


export default component$(() => {
	const loc = useLocation();
	const pluginResource = useResource$<Plugin>(async ({ track, cleanup }) => {
		const response = await fetch('http://osrs-stats:8080/plugins/' + loc.params.name);
		const jsonResponse = await response.json();

		if (jsonResponse.success) return jsonResponse.data;
		else throw new Error('Failed to fetch plugins');
	});

	return (
		<div>
			<Resource
				value={pluginResource}
				onResolved={(plugin) => (
					<div class="rounded-lg border bg-card text-card-foreground shadow-sm w-full max-w-md">
						<div class="flex flex-col p-6 pb-6 space-y-2">
							<h3 class="whitespace-nowrap text-4xl text-orange-600 font-semibold leading-none tracking-tight">{plugin.display}</h3>
							<p class="text-sm text-gray-300">{plugin.description}</p>
							<p class="text-xs text-gray-300 break-words">{plugin.tags}</p>
						</div>
						<div class="p-6 space-y-4">
							<div class="flex flex-col space-y-0.5">
								<div>
									<div class="text-sm font-medium text-gray-500 dark:text-gray-400">Author</div>
									<div>{plugin.author}</div>
								</div>
								<div>
									<div class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Installs</div>
									<div>{formatNumber(plugin.current_installs)}</div>
								</div>
								<div>
									<div class="text-sm font-medium text-gray-500 dark:text-gray-400">All-time High</div>
									<div>{formatNumber(plugin.all_time_high)}</div>
								</div>
								<div>
									<div class="text-sm font-medium text-gray-500 dark:text-gray-400">Released On</div>
									<div>{formatDate(plugin.created_on)}</div>
								</div>
								<div>
									<div class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Update</div>
									<div>{formatDate(plugin.updated_on)}</div>
								</div>
							</div>
						</div>
						<div class=" p-6 flex justify-start gap-4">

							<a class="hover:underline text-orange-500" target="_blank" href={plugin.git_repo}>GitHub</a>
							<a class="hover:underline text-orange-500" target="_blank" href={"https://runelite.net/plugin-hub/show/" + plugin.name}>Runelite</a>
							<a class="hover:underline text-orange-500" target="_blank" href={plugin.support}>Support</a>
						</div>
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
