import React from 'react';

import heroText from './hero-text.twig';

import heroTextData from './hero-text.yml';

/**
 * Storybook Definition.
 */
export default { title: 'Molecules/Hero Text' };

export const heroTextExample = () => (
  <div dangerouslySetInnerHTML={{ __html: heroText(heroTextData) }} />
);
