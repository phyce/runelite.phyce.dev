import {
	component$,
	useStore,
	$,
	Resource,
	useResource$,
	useContextProvider,
	useContext,
	useTask$
} from "@builder.io/qwik";
import Plugin from '~/interfaces/plugin';
import {get} from "~/utils/http";
import {getConfig} from "~/utils/config";
import {globalContextId} from "~/providers/plugins";


export default component$(() => {
	const context = useContext(globalContextId);

	const fetchRandomPlugin = $(async (): Promise<void> => {
		try {
			const randomPlugin = await get<Plugin>("/plugins/random");
			window.location.href = `/${randomPlugin.name}`;
		} catch (error: any) {
			console.error("Error fetching random plugin:", error);
		}
	});

	const pluginsResource = useResource$<Plugin[]>(async ({ track, cleanup }) => {
		return get<Plugin[]>("/plugins");
	});

	useTask$(async function loadPlugins({track}) {
		track(() => context.plugins.value);
	});

	return (
		<header class="flex items-center px-4 py-3">
			<div>
				<Resource
					value={pluginsResource}
					onResolved={(pluginsData) => {
						context.plugins.value = pluginsData;
						return (<></>);
					}}/>
				<a class="inline-block align-middle" href="/" title="Runelite Plugin Stats">
					<img width="64" height="64" src="/img/runelite.png"/>
				</a>&nbsp;
				<a href="/" class="text-white text-3xl inline-block align-middle">RUNELITE PLUGIN STATS</a> v{getConfig().version}
				<small>

				</small>
			</div>
			<div class="flex ml-auto mr-auto items-center space-x-2">
				<button
					onClick$={() => fetchRandomPlugin()}
					class="inline-flex items-center justify-center rounded text-sm bg-orange-700 h-10 px-4 py-2 rounded">
					Random Plugin
				</button>
				{/*<input*/}
				{/*    class="flex h-10 min-w-96 w-full border border-input rounded bg-background px-3 py-2 disabled:opacity-50 rounded-none text-black"*/}
				{/*    placeholder="Search..."*/}
				{/*    type="text"*/}
				{/*/>*/}
				{/*<button class="inline-flex items-center justify-center rounded bg-orange-700 rounded-r p-2">*/}
				{/*  <svg*/}
				{/*      xmlns="http://www.w3.org/2000/svg"*/}
				{/*      width="24"*/}
				{/*      height="24"*/}
				{/*      viewBox="0 0 24 24"*/}
				{/*      fill="none"*/}
				{/*      stroke="currentColor"*/}
				{/*      stroke-width="2"*/}
				{/*      stroke-linecap="round"*/}
				{/*      stroke-linejoin="round"*/}
				{/*      class="h-6 w-6"*/}
				{/*  >*/}
				{/*    <circle cx="11" cy="11" r="8"></circle>*/}
				{/*    <path d="m21 21-4.3-4.3"></path>*/}
				{/*  </svg>*/}
				{/*  <span class="sr-only">Search</span>*/}
				{/*</button>*/}
				<a href="https://github.com/phyce/runelite.phyce.dev" class="github-corner" aria-label="View source on GitHub" title="View source on GitHub">
					<svg width="80" height="80" viewBox="0 0 250 250" style="fill:rgba(154, 52, 18, 0.5); color:rgba(0,0,0,0.7); position: absolute; top: 0; border: 0; right: 0;" aria-hidden="true">
						<path d="M0,0 L115,115 L130,115 L142,142 L250,250 L250,0 Z"></path>
						<path d="M128.3,109.0 C113.8,99.7 119.0,89.6 119.0,89.6 C122.0,82.7 120.5,78.6 120.5,78.6 C119.2,72.0 123.4,76.3 123.4,76.3 C127.3,80.9 125.5,87.3 125.5,87.3 C122.9,97.6 130.6,101.9 134.4,103.2" fill="currentColor" style="transform-origin: 130px 106px;" class="octo-arm"></path>
						<path d="M115.0,115.0 C114.9,115.1 118.7,116.5 119.8,115.4 L133.7,101.6 C136.9,99.2 139.9,98.4 142.2,98.6 C133.8,88.0 127.5,74.4 143.8,58.0 C148.5,53.4 154.0,51.2 159.7,51.0 C160.3,49.4 163.2,43.6 171.4,40.1 C171.4,40.1 176.1,42.5 178.8,56.2 C183.1,58.6 187.2,61.8 190.9,65.4 C194.5,69.0 197.7,73.2 200.1,77.6 C213.8,80.2 216.3,84.9 216.3,84.9 C212.7,93.1 206.9,96.0 205.4,96.6 C205.1,102.4 203.0,107.8 198.3,112.5 C181.9,128.9 168.3,122.5 157.7,114.1 C157.9,116.9 156.7,120.9 152.7,124.9 L141.0,136.5 C139.8,137.7 141.6,141.9 141.8,141.8 Z" fill="currentColor" class="octo-body"></path>
					</svg>
				</a>
			</div>
		</header>
	);
});


