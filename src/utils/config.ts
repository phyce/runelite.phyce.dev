// config-provider.ts
import {ResourceReturn, useResource$} from '@builder.io/qwik';
import config from '~/config.json';

export interface Config {
	author: string;
	version: string;
}

export const getConfig = (): Config => {
	return config;
};