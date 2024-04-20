import { component$ } from "@builder.io/qwik";
import { useServerTimeLoader } from "../../../routes/layout";
import styles from "./footer.module.css";

export default component$(() => {
  const serverTime = useServerTimeLoader();

  return (
    <footer>
      <div class="py-2">
        <a href="https://phyce.dev/" target="_blank" class={styles.anchor}>
          <span>Made by <text class="text-orange-500">Phyce</text></span>
        </a>
      </div>
    </footer>
  );
});
