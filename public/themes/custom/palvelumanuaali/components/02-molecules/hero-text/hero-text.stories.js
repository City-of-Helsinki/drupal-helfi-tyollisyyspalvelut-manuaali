import heroText from './hero-text.twig';

import heroTextData from './hero-text.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Hero Text' };

export const heroTextExample = () => heroText(heroTextData);
