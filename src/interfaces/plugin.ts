export default interface Plugin {
	id: number;
	name: string;
	git_repo: string;
	display: string;
	author: string;
	support: string;
	description: string;
	tags: string;
	warning: string;
	created_on: string;
	updated_on: string;
	all_time_high: number;
	current_installs: number;
}