import { createContextId, type Signal, useSignal } from '@builder.io/qwik';
import Plugin from '~/interfaces/plugin';

export interface GlobalContext {
	plugins: Signal<Plugin[]>;
}

export const globalContextId = createContextId<GlobalContext>('plugin.context');

// export function usePluginsProvider() {
// 	const plugins = useSignal<Plugin[]>([]);
//
// 	return {
// 		plugins,
// 	};
// }