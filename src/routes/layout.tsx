import {component$, Slot, useContextProvider, useSignal, useStyles$} from "@builder.io/qwik";
import { routeLoader$ } from "@builder.io/qwik-city";
import type { RequestHandler } from "@builder.io/qwik-city";
import {globalContextId, useGlobalProvider} from "~/providers/global";

import Header from "../components/starter/header/header";
import Footer from "../components/starter/footer/footer";

import styles from "./styles.css?inline";
import {get} from "~/utils/http";
import Plugin from "~/interfaces/plugin";
import {handleParams} from "~/middleware/handleParams";
import {getRecordCookie} from "~/utils/utils";

export const onGet: RequestHandler = async (requestEvent) => {
	// Control caching for this request for best performance and to reduce hosting costs:
	// https://qwik.dev/docs/caching/
	requestEvent.cacheControl({
		// Always serve a cached response by default, up to a week stale
		staleWhileRevalidate: 60 * 60 * 24 * 7,
		// Max once every 5 seconds, revalidate on the server to get a fresh version of this page
		maxAge: 5,
	});

	handleParams(requestEvent);
	return await requestEvent.next();
};

export const useGetData = routeLoader$(async (requestEvent) => {
	const parameters = getRecordCookie(requestEvent);

	const plugins = await get<Plugin[]>("plugins", requestEvent.env.get('BACKEND_API_URL'), parameters);
	return {
		pluginData: plugins as Plugin[],
		filterData: parameters,
	};
})

export const useServerTimeLoader = routeLoader$(() => {
	return {
		date: new Date().toISOString(),
	};
});

export default component$(() => {
	useStyles$(styles);
	const {pluginData, filterData} = useGetData().value;
	const global = useGlobalProvider({pluginData, filterData});
	useContextProvider(globalContextId, global);
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