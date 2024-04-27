import {component$, Slot, useContextProvider, useSignal, useStyles$} from "@builder.io/qwik";
import { routeLoader$ } from "@builder.io/qwik-city";
import type { RequestHandler } from "@builder.io/qwik-city";
import {GlobalContext, globalContextId} from "~/providers/plugins";
import Plugin from "~/interfaces/plugin";

import Header from "../components/starter/header/header";
import Footer from "../components/starter/footer/footer";

import styles from "./styles.css?inline";

export const onGet: RequestHandler = async ({ cacheControl }) => {
	// Control caching for this request for best performance and to reduce hosting costs:
	// https://qwik.dev/docs/caching/
	cacheControl({
		// Always serve a cached response by default, up to a week stale
		staleWhileRevalidate: 60 * 60 * 24 * 7,
		// Max once every 5 seconds, revalidate on the server to get a fresh version of this page
		maxAge: 5,
	});
};

export const useServerTimeLoader = routeLoader$(() => {
	return {
		date: new Date().toISOString(),
	};
});

export default component$(() => {
	useStyles$(styles);
	// const plugins = usePluginsProvider();
	const context: GlobalContext = {
		plugins: useSignal<Plugin[]>([])
	};
	useContextProvider(globalContextId, context);
	return (
		<>
			<Header />
			<main>
				<Slot />
			</main>
			<Footer />
		</>
	);
});
