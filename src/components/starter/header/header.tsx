import { component$ } from "@builder.io/qwik";
import styles from "./header.module.css";

export default component$(() => {
  return (
    <header>
      <div class={["container", styles.wrapper]}>
        <div>
          <a class="inline-block align-middle" href="/" title="Runelite Plugin Stats">
            <img width="64" height="64" src="/img/runelite.png"/>
          </a>&nbsp;
          <h1 class="text-3xl inline-block align-middle">RUNELITE PLUGIN STATS</h1>
        </div>
      </div>
    </header>
  );
});
