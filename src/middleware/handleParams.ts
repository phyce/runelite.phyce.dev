import {RequestEvent} from "@builder.io/qwik-city";

export const handleParams = ((requestEvent: RequestEvent) => {
	const paramCookie = requestEvent.cookie.get('getParams');

	let parameters: Record<string, string> = {};

	if (paramCookie) parameters = JSON.parse(paramCookie.value);

	requestEvent.url.searchParams.forEach((value, key) => {
		if (value === '') delete parameters[key];
		else parameters[key] = value;
	});

	const paramsJson = JSON.stringify(parameters);
	requestEvent.cookie.set('getParams', paramsJson, {path: '/'});
});

