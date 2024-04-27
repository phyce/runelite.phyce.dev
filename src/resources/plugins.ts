import { createContextId, type Signal, useSignal } from '@builder.io/qwik';
import Plugin from '~/interfaces/plugin';

export const PluginsContext = createContextId<{
	plugins: Signal<Plugin[]>;
}>('plugin.context');

export function usePluginsProvider() {
	const plugins = useSignal<Plugin[]>([]);

	return {
		plugins,
	};
}