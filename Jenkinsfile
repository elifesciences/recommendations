elifePipeline {
    def commit
    DockerImage image
    stage 'Checkout', {
        checkout scm
        commit = elifeGitRevision()
    }

    node('containers-jenkins-plugin') {
        stage 'Build image', {
                checkout scm
                dockerComposeBuild commit
        }

        stage 'Project tests', {
            dockerProjectTests 'recommendations', commit
        }

        elifeMainlineOnly {
            stage 'Push image', {
                image = DockerImage.elifesciences(this, "recommendations", commit).push()
            }
        }
    }

    elifeMainlineOnly {
        stage 'Deploy on continuumtest', {
            lock('recommendations--continuumtest') {
                builderDeployRevision 'recommendations--continuumtest', commit
                builderSmokeTests 'recommendations--continuumtest', '/srv/recommendations'
            }
        }

        stage 'Approval', {
            elifeGitMoveToBranch commit, 'approved'
            node('containers-jenkins-plugin') {
                image.pull().tag('approved').push()
            }
        }
    }
}
