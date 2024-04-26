import {component$, Resource, useResource$, useSignal, useVisibleTask$} from '@builder.io/qwik';
import type {DocumentHead} from "@builder.io/qwik-city";
import Plugin from '~/interfaces/plugin';
import PluginHistoryData from '~/interfaces/pluginHistoryData';
import { formatDate, formatDateTime, formatNumber } from '~/utils/utils';
import { useLocation } from '@builder.io/qwik-city';
import { Chart, registerables } from 'chart.js/auto';
import {get} from "~/utils/http";


export default component$(() => {
	const loc = useLocation();
	const pluginResource = useResource$<Plugin>(async ({ track, cleanup }) => {
		return get<Plugin>("/plugins/" + loc.params.name);
	});

	const pluginInstallDataResource = useResource$<PluginHistoryData[]>( async ({ track, cleanup }) => {
		return get<PluginHistoryData[]>("/plugins/" + loc.params.name + "/history");
	});

	const myChart = useSignal<HTMLCanvasElement>();
	const historyDataSignal = useSignal<PluginHistoryData[] | null>(null);



	useVisibleTask$(() => {
		const pageLoadTime = new Date();

		const intervalId = setInterval(() => {
			const currentTime = new Date();
			const currentMinute = currentTime.getMinutes();
			const elapsedTime = (currentTime.getTime() - pageLoadTime.getTime()) / 60000; // Elapsed time in minutes

			// Check if it's the 55th minute and at least a minute has elapsed since page load
			if (currentMinute === 1 && elapsedTime > 1) {
				window.location.reload();
			}
		}, 30000);

		const historyData = historyDataSignal.value;
		if (myChart?.value) {
			Chart.register(...registerables);
			new Chart(myChart.value, {
				type: 'line',
				data: {
					labels: historyData?.map((data: { date: string; }) => data.date),
					datasets: [{
						label: 'Install Count Over Time',
						data: historyData?.map((data: { count: number; }) => data.count),
						borderColor: 'rgb(255,108,33)',
						backgroundColor: 'rgb(197,72,4)',
						pointRadius: 4,
						pointHoverRadius: 7
					}]
				},
				options: {
					scales: {
						x: {
							ticks: {
								color: "#FF6C21FF"
							}
						},
						y: {
							ticks: {
								color: "#FF6C21FF"
							},
							beginAtZero: true
						}
					}
				}
			});
		}
	});


	return (
		<div class="flex flex-col sm:flex-row w-full h-full">
			<Resource
				value={pluginResource}
				onResolved={(plugin) => (
					<div class="w-full sm:w-1/3 rounded-lg border bg-card text-card-foreground shadow-sm w-full max-w-md">
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
						<div class="p-6 flex justify-start gap-4">

							<a class="hover:underline text-orange-500" target="_blank" href={plugin.git_repo}>GitHub</a>
							<a class="hover:underline text-orange-500" target="_blank" href={"https://runelite.net/plugin-hub/show/" + plugin.name}>Runelite</a>
							<a class="hover:underline text-orange-500" target="_blank" href={plugin.support}>Support</a>
						</div>
					</div>
				)}
			/>
			<Resource
				value={pluginInstallDataResource}
				onResolved={(historyData) => {
					historyDataSignal.value = historyData; // Update the signal with the resolved data
					return (
						<div class="w-full sm:w-2/3">
							<canvas ref={myChart} id="historyChart"></canvas>
						</div>
					);
				}}
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
