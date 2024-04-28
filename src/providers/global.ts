import {
	createContextId,
	ReadonlySignal,
	type Signal,
	useSignal
} from '@builder.io/qwik';
import Plugin from '~/interfaces/plugin';

export interface GlobalContext {
	plugins: Signal<Plugin[]>;
}

export const globalContextId = createContextId<GlobalContext>('plugin.context');

interface GlobalProviderParams {
	pluginData : Plugin[];
}

export function useGlobalProvider(params: GlobalProviderParams): GlobalContext {
	const { pluginData = [] } = params;
	const plugins = useSignal<Plugin[]>(pluginData);

	return {
		plugins,
	};
}