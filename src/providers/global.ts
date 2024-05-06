import {
	createContextId,
	ReadonlySignal,
	type Signal,
	useSignal
} from '@builder.io/qwik';
import Plugin from '~/interfaces/plugin';

export interface GlobalContext {
	plugins: Signal<Plugin[]>;
	filters: Signal<Record<string, string>>;
}

export const globalContextId = createContextId<GlobalContext>('plugin.context');

interface GlobalProviderParams {
	pluginData : Plugin[];
	filterData?: Record<string, string>;
}

export function useGlobalProvider(params: GlobalProviderParams): GlobalContext {
	const { pluginData = [] } = params;
	const plugins = useSignal<Plugin[]>(pluginData);
	const filters = useSignal<Record<string, string>>(params.filterData || {});

	return {
		plugins,
		filters
	};
}