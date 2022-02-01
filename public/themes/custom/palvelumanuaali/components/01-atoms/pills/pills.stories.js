// Buttons Stories
import pill from './twig/pill.twig';

import pillData from './twig/pill.yml';
import pillAltData from './twig/pill-alt.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Atoms/Pill' };

export const twig = () => pill(pillData);

export const twigAlt = () => pill(pillAltData);
