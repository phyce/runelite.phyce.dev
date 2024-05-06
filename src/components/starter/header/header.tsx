import {
	component$,
	useStore,
	$,
	Resource,
	useResource$,
	useContextProvider,
	useContext,
	useSignal, useTask$
} from "@builder.io/qwik";
import Plugin from '~/interfaces/plugin';
import {get} from "~/utils/http";
import {getConfig} from "~/utils/config";
import {globalContextId} from "~/providers/global";


export default component$(() => {
	const globalContext = useContext(globalContextId);
	const pluginSignal = useSignal<Plugin[]>(globalContext.plugins.value);
	const searchInput = useSignal<string>('');
	const searchInputVisible = useSignal<boolean>(false);

	const fetchRandomPlugin = $(async (): Promise<void> => {
		try {
			const randomPlugin = await get<Plugin>("plugins/random");
			window.location.href = `/${randomPlugin.name}`;
		} catch (error: any) {
			console.error("Error fetching random plugin:", error);
		}
	});

	const handleSearchChange = $((event: Event) => {
		const target = event.target as HTMLInputElement;
		searchInput.value = target.value;
	});

	const searchResultSignal = useSignal<Plugin[]>([]);
	useTask$(({ track }) => {
		track(() => searchInput.value);

		function searchScore(searchInput: string, plugin: Plugin): number {
			const searchTerms = searchInput.toLowerCase().split(' ');
			let score = 0;

			const wordsInFields = new Set<string>();
			const fieldsToSearch = [plugin.name, plugin.display, plugin.author, plugin.description, plugin.tags];

			fieldsToSearch.forEach(field => {
				field.toLowerCase().split(' ').forEach(word => {
					wordsInFields.add(word);
				});
			});

			searchTerms.forEach(term => {
				if (wordsInFields.has(term)) score += 2;
				else wordsInFields.forEach(word => {
					if (word.includes(term)) score += 1;
				});
			});

			return score;
		}

		searchResultSignal.value = pluginSignal.value?.map(plugin => ({
			...plugin,
			score: searchScore(searchInput.value, plugin)
		}))
			.filter(plugin => plugin.score > 0)
			.sort((a, b) => b.score - a.score);
	});

	return (
		<header class="flex flex-col sm:flex-row items-center px-4 py-3 w-full">
			<div class="w-full sm:w-4/12 flex justify-between items-center mr-4">
				<a class="inline-block align-middle" href="/" title="Runelite Plugin Stats">
					<img width="64" height="64" src="/img/runelite.png"/>
				</a>&nbsp;
				<a href="/" class="text-white text-3xl inline-block align-middle">Runelite Plugin Stats</a> v{getConfig().version}
			</div>
			<div class="flex flex-col sm:flex-row w-8/12 items-center space-y-2 sm:space-y-0 sm:space-x-2">
				<button
					onClick$={() => fetchRandomPlugin()}
					class="min-w-48 flex-grow sm:flex-grow-0 w-full sm:w-auto inline-flex items-center justify-center rounded text-sm bg-orange-700 h-10 px-2 py-1">
					Random Plugin
					<svg
						class="ml-2"
						width="28"
						height="28"
						style=""
						xmlns="http://www.w3.org/2000/svg"
						viewBox="0 0 640 512">
						<path fill="#ffffff" d="M274.9 34.3c-28.1-28.1-73.7-28.1-101.8 0L34.3 173.1c-28.1 28.1-28.1 73.7 0 101.8L173.1 413.7c28.1 28.1 73.7 28.1 101.8 0L413.7 274.9c28.1-28.1 28.1-73.7 0-101.8L274.9 34.3zM200 224a24 24 0 1 1 48 0 24 24 0 1 1 -48 0zM96 200a24 24 0 1 1 0 48 24 24 0 1 1 0-48zM224 376a24 24 0 1 1 0-48 24 24 0 1 1 0 48zM352 200a24 24 0 1 1 0 48 24 24 0 1 1 0-48zM224 120a24 24 0 1 1 0-48 24 24 0 1 1 0 48zm96 328c0 35.3 28.7 64 64 64H576c35.3 0 64-28.7 64-64V256c0-35.3-28.7-64-64-64H461.7c11.6 36 3.1 77-25.4 105.5L320 413.8V448zM480 328a24 24 0 1 1 0 48 24 24 0 1 1 0-48z"/>
					</svg>
				</button>
				<input class="flex-grow w-full border border-input px-3 py-2 rounded text-black ring-2 focus:ring-orange-500"
					placeholder="Search by name, author, tags, description..."
					type="text"
					value={searchInput.value}
					onInput$={handleSearchChange}
					onFocus$={() => {searchInputVisible.value=true}}
					onBlur$={() => {
						setTimeout(() => {
							searchInputVisible.value=false;
						}, 200);
					}}
				/>
				{searchInputVisible.value && (
					<div class="absolute top-20 bg-neutral-700 rounded mt-1 w-full z-50 text-white shadow-lg overflow-y-auto max-h-96 scrollbar-custom">
						{searchResultSignal.value?.map(plugin => (
							<a href={"/" + plugin.name}><div key={plugin.id} class="p-2 hover:bg-neutral-600 text-white">
								{plugin.display}
							</div></a>
						))}
					</div>
				)}
			</div>
			<a href="https://github.com/phyce/runelite.phyce.dev" class="github-corner" aria-label="View source on GitHub" title="View source on GitHub">
				<svg width="80" height="80" viewBox="0 0 250 250" style="fill:rgba(154, 52, 18, 0.5); color:rgba(0,0,0,0.7); position: absolute; top: 0; border: 0; right: 0;" aria-hidden="true">
					<path d="M0,0 L115,115 L130,115 L142,142 L250,250 L250,0 Z"></path>
					<path d="M128.3,109.0 C113.8,99.7 119.0,89.6 119.0,89.6 C122.0,82.7 120.5,78.6 120.5,78.6 C119.2,72.0 123.4,76.3 123.4,76.3 C127.3,80.9 125.5,87.3 125.5,87.3 C122.9,97.6 130.6,101.9 134.4,103.2" fill="currentColor" style="transform-origin: 130px 106px;" class="octo-arm"></path>
					<path d="M115.0,115.0 C114.9,115.1 118.7,116.5 119.8,115.4 L133.7,101.6 C136.9,99.2 139.9,98.4 142.2,98.6 C133.8,88.0 127.5,74.4 143.8,58.0 C148.5,53.4 154.0,51.2 159.7,51.0 C160.3,49.4 163.2,43.6 171.4,40.1 C171.4,40.1 176.1,42.5 178.8,56.2 C183.1,58.6 187.2,61.8 190.9,65.4 C194.5,69.0 197.7,73.2 200.1,77.6 C213.8,80.2 216.3,84.9 216.3,84.9 C212.7,93.1 206.9,96.0 205.4,96.6 C205.1,102.4 203.0,107.8 198.3,112.5 C181.9,128.9 168.3,122.5 157.7,114.1 C157.9,116.9 156.7,120.9 152.7,124.9 L141.0,136.5 C139.8,137.7 141.6,141.9 141.8,141.8 Z" fill="currentColor" class="octo-body"></path>
				</svg>
			</a>
		</header>
	);
});


